<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Rol;
use App\Models\User;
use App\Models\Notice;
use App\Models\Booking;
use App\Models\Departament;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\PeoplesXDepartaments;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Notifications\RealtimeNotification;

class UserController extends Controller
{
    //
    public function store(Request $request)
    {

        $validated = $this->validateFieldsFromInput($request->all());

        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }
        $user = User::create([
            'name'      =>  $request->name,
            'email'     =>  $request->email,
            'username'  =>  $request->username,
            'phone'     =>  $request->phone,
            'password'  =>  bcrypt($request->password) ,
            'rol_id'    =>  $request->idRol,
            'parentesco' =>  $request->parentesco ?? null,
            'active_time' =>  $request->active_time ?? null,
        ]);
        $this->afteSaveUser($user, $request);
        return $this->returnSuccess(200, 'ok');
    }

    /**
     * Crea usuarios temporales (Airbnb) o habitantes/familiares del propietario.
     *
     * @param string tipo - 'airbnb' para alquiler temporal, 'familiar' para familiares que viven con el propietario
     * @param string name - Nombre completo
     * @param string email - Correo electrónico (único)
     * @param int idApartament - ID del departamento
     * @param string active_time - (Solo Airbnb) Fecha de fin del alquiler (Y-m-d)
     * @param string parentesco - (Solo Familiar) Relación: padre, madre, hijo, hija, cónyuge, etc.
     * @param string phone - (Opcional) Teléfono
     * @param string password - (Opcional) Si no se envía, se genera automáticamente
     */
    public function storeResidentUser(Request $request)
    {
        $validated = $this->validateTemporaryOrResidentInput($request->all());

        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        if ($request->user()->rol_id === Rol::PROPIETARIO) {
            $departament = Departament::find($request->idApartament);
            if (!$departament || $departament->user_id !== $request->user()->id) {
                return $this->returnFail(403, 'No tiene permiso para registrar usuarios en este departamento.');
            }
        }

        $tipo = $request->type;
        $isAirbnb = $tipo === 'airbnb';

        $username = $request->username ?? Str::lower(Str::random(8)) . '_' . time();
        $password = $request->password ?? Str::password(12);
        $date = $isAirbnb ?   strtotime($request->active_time) : null;
        $userData = [
            'name'       => $request->name,
            'email'      => $request->email,
            'username'   => $username,
            'phone'      => $request->phone ?? null,
            'password'   => bcrypt($password),
            'rol_id'     => $isAirbnb ? Rol::AIRBNB : Rol::FAMILIAR,
            'parentesco' => $isAirbnb ? null : $request->parentesco,
            'active_time' => $isAirbnb ? date('Y-m-d', $date) : null,
        ];

        $user = User::create($userData);

        $people = PeoplesXDepartaments::create([
            'user_id'        => $user->id,
            'departament_id' => $request->idApartament,
            'type'           => $isAirbnb ? Rol::AIRBNB : Rol::FAMILIAR,
            'created_by'     => $request->user()->id,
        ]);

        if ($isAirbnb) {
            $this->setAvailableComunAreaToReserve($people);
        }

        return $this->returnSuccess(200, [
            'message' => $isAirbnb ? 'Usuario temporal (Airbnb) creado correctamente' : 'Familiar/habitante registrado correctamente',
            'user_id' => $user->id,
        ]);
    }

    /**
     * Obtiene los residentes y usuarios Airbnb registrados por el usuario autenticado (propietario).
     *
     * @param Request $request - Usa request->user()->id como created_by
     */
    public function getResident(Request $request)
    {
        $residents = PeoplesXDepartaments::with(['user.rol', 'departament'])
            ->where('created_by', $request->user()->id)
            ->whereIn('type', [Rol::FAMILIAR, Rol::AIRBNB])
            ->get()
            ->map(function ($people) {
                return [
                    'id'           => $people->id,
                    'type'         => $people->type,
                    'type_label'   => $people->type === Rol::AIRBNB ? 'Airbnb' : 'Familiar',
                    'user'         => $people->user,
                    'departament'  => $people->departament,
                    'active_time'  => $people->user?->active_time,
                    'parentesco'   => $people->user?->parentesco,
                ];
            });

        return $this->returnSuccess(200, $residents);
    }

    private function validateTemporaryOrResidentInput(array $inputs): array
    {
        $rules = [
            'type'         => ['required', 'in:airbnb,familiar'],
            'name'         => ['required', 'regex:/^[a-zA-Z-À-ÿ .]+$/i'],
            'email'        => ['required', 'email', 'unique:users'],
            'idApartament' => ['required', 'integer', 'exists:departaments,id'],
        ];

        $type = $inputs['type'] ?? null;
        if ($type === 'airbnb') {
            $rules['active_time'] = ['required', 'date', 'after_or_equal:today'];
        }
        if ($type === 'familiar') {
            $rules['parentesco'] = ['required', 'string', 'max:50'];
        }

        $messages = [
            'type.required'     => 'El tipo de usuario es requerido.',
            'type.in'           => 'El tipo debe ser "airbnb" o "familiar".',
            'name.required'     => 'El nombre es requerido.',
            'name.regex'        => 'Nombre no válido.',
            'email.required'    => 'El correo electrónico es requerido.',
            'email.email'       => 'Correo electrónico no válido.',
            'email.unique'      => 'El correo ya está registrado.',
            'idApartament.required' => 'El departamento es requerido.',
            'idApartament.exists'   => 'El departamento no existe.',
            'active_time.required'  => 'La fecha de fin del alquiler es requerida para usuarios Airbnb.',
            'active_time.after_or_equal' => 'La fecha de fin debe ser hoy o posterior.',
            'parentesco.required'   => 'El parentesco es requerido para familiares.',
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();
        return $validator->all();
    }

    private function afteSaveUser($user, $request){
        if ($request->idApartament != 0 && $request->user()->rol_id == Rol::ADMIN) {
            Departament::find($request->idApartament)->update([
                'user_id' => $user->id
            ]);
        }
    }
    public function getOwners(Request $request)
    {
        $dist =  $request->rol == 1 ? '!=' : '==';

        $owners = User::with(['apartaments', 'rol'])
        ->where('rol_id', $dist, $request->rol)
        ->orderBy('name', 'asc')
        ->where('id', '!=', $request->user()->id)
        ->get();

        return $this->returnSuccess(200, $owners);
    }
    public function getCountPendingsForAdmin()
    {
        $noticeTypeForAnnunce = 2;
        $waitStatus = [
            'reserve' => 2,
            'announces' => 1
        ];
        $reservesPendings = Booking::where('status', $waitStatus['reserve'])->get();
        $announcesPendings = Notice::where('status', $waitStatus['announces'])
        ->where('type', $noticeTypeForAnnunce)->get();

        return $this->returnSuccess(200, [
            'reserves' =>  $reservesPendings,
            'announces' => $announcesPendings
        ]);
    }
    public function getAllUserWithPublish()
    {
         $users = User::whereHas('announces')->get();
         return $this->returnSuccess(200, $users);
    }
    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'name'      => ['required', 'regex:/^[a-zA-Z-À-ÿ .]+$/i'],
            'email'     => ['required', 'email', 'unique:users'],
            'username'  => ['required', 'unique:users', 'regex:/^[a-zA-Z-À-ÿ0-9 .]+$/i'],
            'password'  => ['required', 'min:8'],

        ];
        $messages = [
            'name.required'     => 'El nombre es requerido.',
            'name.regex'        => 'Nombre no valido',
            'email.required'    => 'El email es requerido.',
            'email.unique'      => 'El email ya esta registrado.',
            'email.email'       => 'Email no valido',
            'username.required' => 'Nombre de usuario es requerido',
            'username.unique'   => 'Nombre de usuario ya esta registrado.',
            'username.regex'    => 'Nombre de usuario no valido',
            'password.required'     => 'La contraseña es requerida',
            'password.min'          => 'La contraseña debe tener un minimo de 8 caracteres'
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();
        return $validator->all() ;
    }
    public function saveTokenMovile(Request $request)
    {
        try {
             $request->validate(['token' => 'required|string']);
        
        // Asumiendo que usas Auth
            $user = $request->user();
            $user->update(['device_token' => $request->token]);
        } catch (Exception $th) {
            return $this->returnFail(400, $th->getMessage());
            //throw $th;
        }
       

        return $this->returnSuccess(200, 'ok');
    }
    public function pruebaRealtimeNotification() 
    {
        $user = User::find(8);
        try {
            $user->notify(new RealtimeNotification(
                title: 'Pago de reserva aceptado',
                message: 'Tu pago por la reserva #0004588 fue aprobada.',
                url: '/client/reserves/view/2',
                meta: [
                    'booking_id' => 2,
                    'icon' => 'eva-checkmark-outline',
                ]
            ));
            return $this->returnSuccess(200, 'bien');
        } catch (Exception $e) {

            return $this->returnSuccess(400, $e->getMessage());
            // Silenciar errores de notificación para no romper el flujo
        }

    }
    public function setAvailableComunAreaToReserve(?PeoplesXDepartaments $people = null)
    {
        // TODO: Implementar lógica para habilitar áreas comunes a reservar para el usuario
    }
}
