<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AirbnbRent;
use App\Models\Booking;
use App\Models\Departament;
use App\Models\Notice;
use App\Models\PeoplesXDepartaments;
use App\Models\Rol;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\RealtimeNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserController extends Controller
{
    //
    public function getUserById($id)
    {
        $user = User::with(['units'])->find($id);
        if (! $user) {
            return $this->returnFail(404, 'Usuario no encontrado');
        }

        return $this->returnSuccess(200, $user);
    }

    public function store(Request $request)
    {

        $validated = $this->validateFieldsFromInput($request->all());

        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
            'rol_id' => $request->idRol,
            'parentesco' => $request->parentesco ?? null,
            'active_time' => $request->active_time ?? null,
            'is_first_time' => 1,
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

        // Iniciamos la transacción para asegurar integridad total
        DB::beginTransaction();

        try {
            if ($request->user()->rol_id === Rol::PROPIETARIO) {
                $departament = Departament::find($request->idApartament);
                if (! $departament || $departament->user_id !== $request->user()->id) {
                    throw new Exception('No tiene permiso para registrar usuarios en este departamento.');
                }
            }

            $tipo = $request->type;
            $isAirbnb = $tipo === 'airbnb';
            $isFamiliar = $tipo === 'familiar';
            $isInquilino = $tipo === 'inquilino';

            $rolId = Rol::FAMILIAR;
            if ($isAirbnb) {
                $rolId = Rol::AIRBNB;
            }
            if ($isInquilino) {
                $rolId = Rol::INQUILINO;
            }

            if ($isInquilino) {
                $existingTenant = PeoplesXDepartaments::where('departament_id', $request->idApartament)
                    ->where('type', Rol::INQUILINO)
                    ->whereHas('user', fn ($q) => $q->where('status', '!=', 3)->whereNull('deleted_at'))
                    ->exists();
                if ($existingTenant) {
                    throw new Exception('El departamento ya tiene un inquilino activo.');
                }
            }

            // Preparación de credenciales
            $username = $request->username ?? Str::lower(Str::random(8)).'_'.time();
            $password = $request->password ?? Str::password(12);
            $activeTime = $isAirbnb ? strtotime($request->active_time) : null;
            $dateEnd = $isAirbnb ? strtotime($request->end_time) : null;

            // 1. Crear el Usuario principal (el que accede a la app)
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'username' => $username,
                'phone' => $request->phone ?? null,
                'password' => bcrypt($password),
                'rol_id' => $rolId,
                'parentesco' => $isFamiliar ? $request->parentesco : null,
                'status' => $isAirbnb ? 3 : 1,
                'active_time' => $isAirbnb ? date('Y-m-d', $activeTime) : null,
                'end_time' => $isAirbnb ? date('Y-m-d', $dateEnd) : null,

            ]);

            // 2. Vincular usuario al departamento
            $people = PeoplesXDepartaments::create([
                'user_id' => $user->id,
                'departament_id' => $request->idApartament,
                'type' => $rolId,
                'created_by' => $request->user()->id,
            ]);

            if ($isAirbnb) {
                $airbnbData = $request->input('airbnb');

                // 3. Registrar la Renta en AirbnbRent
                $rent = AirbnbRent::create([
                    'departament_id' => $request->idApartament,
                    'assing_to' => $user->id,
                    'name_to' => $airbnbData['nameTo'],
                    'created_by' => $request->user()->id,
                    'quantity' => $airbnbData['quantity'],
                    'init_day' => date('Y-m-d', strtotime($airbnbData['init_time'])),
                    'end_date' => date('Y-m-d', strtotime($airbnbData['end_time'])),
                    'status' => 1,
                ]);

                // 4. Registrar a los acompañantes como Visitas (Type 3)
                if (isset($airbnbData['guests']) && is_array($airbnbData['guests'])) {
                    foreach ($airbnbData['guests'] as $index => $guest) {
                        // 1. Creamos el registro primero, dejando la foto en null por ahora
                        $visit = Visit::create([
                            'departament_id' => $request->idApartament,
                            'created_by' => $request->user()->id,
                            'fullname' => $guest['name'],
                            'dni' => $guest['document'],
                            'type' => 3,
                            'photo' => null, // Se actualizará después
                            'date' => date('Y-m-d', strtotime($airbnbData['init_time'])),
                            'hour' => date('H:i'),
                            'status' => 1,
                            'airbnb_rent_id' => $rent->id,
                        ]);

                        // 2. Extraemos el archivo usando notación de puntos de Laravel
                        $photoFile = $request->file("airbnb.guests.{$index}.photo");

                        // 3. Si el archivo existe y es válido, llamamos a la nueva función
                        if ($photoFile && $photoFile->isValid()) {
                            $this->uploadGuestPhoto($visit, $photoFile);
                        }
                    }
                }
                if (method_exists($this, 'setAvailableComunAreaToReserve')) {
                    $this->setAvailableComunAreaToReserve($people);
                }
            }
            DB::commit();

            return $this->returnSuccess(200, [
                'message' => $isAirbnb ? 'Airbnb y acompañantes registrados' : 'Residente registrado con éxito',
                'user_id' => $user->id,
                'ss' => isset($airbnbData['guests']) && is_array($airbnbData['guests']) ? 'si' : 'no',
                'fff' => isset($guestFiles[0]['photo']) && $guestFiles[0]['photo']->isValid() ? 'si' : 'no',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error en storeResidentUser: '.$e->getMessage());

            return $this->returnFail(500, 'No se pudo completar el registro: '.$e->getMessage());
        }
    }

    private function uploadGuestPhoto($visit, $photoFile)
    {
        $relativePath = '';
        $rand = rand(1000000, 9999999);
        $fileName = trim(str_replace(' ', '_', $visit->id));
        $extension = $photoFile->extension();

        $imageName = "{$rand}_{$fileName}.{$extension}";

        // Ruta relativa que se guardará en la base de datos
        $relativePath = "/public/images/airbnb/guest/{$imageName}";

        // Ruta física absoluta en el servidor donde se moverá
        $destinationPath = public_path().'/images/airbnb/guest/';

        // Movemos el archivo a la carpeta pública
        $photoFile->move($destinationPath, $relativePath);

        // Actualizamos el registro de la visita con la ruta y guardamos
        $visit->photo = $relativePath;
        $visit->save();
    }

    /**
     * Obtiene los residentes y usuarios Airbnb registrados por el usuario autenticado (propietario).
     *
     * @param  Request  $request  - Usa request->user()->id como created_by
     */
    public function getResident(Request $request)
    {
        $residents = PeoplesXDepartaments::with(['user.rol', 'departament'])
            ->where('created_by', $request->user()->id)
            ->whereIn('type', [Rol::FAMILIAR, Rol::AIRBNB, Rol::INQUILINO])
            ->get()
            ->map(function ($people) {
                $typeLabel = 'Familiar';
                if ($people->type === Rol::AIRBNB) {
                    $typeLabel = 'Airbnb';
                }
                if ($people->type === Rol::INQUILINO) {
                    $typeLabel = 'Inquilino';
                }

                return [
                    'id' => $people->id,
                    'type' => $people->type,
                    'type_label' => $typeLabel,
                    'user' => $people->user,
                    'departament' => $people->departament,
                    'active_time' => $people->user?->active_time,
                    'parentesco' => $people->user?->parentesco,
                ];
            });

        return $this->returnSuccess(200, $residents);
    }

    private function validateTemporaryOrResidentInput(array $inputs): array
    {
        $rules = [
            'type' => ['required', 'in:airbnb,familiar,inquilino'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'idApartament' => ['required', 'integer', 'exists:departaments,id'],
        ];

        if (($inputs['type'] ?? '') === 'airbnb') {
            $rules['active_time'] = ['required', 'date'];
            $rules['airbnb.nameTo'] = ['required', 'string'];
            $rules['airbnb.quantity'] = ['required', 'integer', 'min:1'];
            $rules['airbnb.init_time'] = ['required', 'date'];
            $rules['airbnb.end_time'] = ['required', 'date', 'after_or_equal:airbnb.init_time'];

            // Validaciones para cada acompañante
            $rules['airbnb.guests'] = ['required', 'array'];
            $rules['airbnb.guests.*.name'] = ['required', 'string'];
            $rules['airbnb.guests.*.document'] = ['required', 'string'];
            $rules['airbnb.guests.*.photo'] = ['nullable', 'image', 'max:4096']; // Max 4MB
        } elseif (($inputs['type'] ?? '') === 'familiar') {
            $rules['parentesco'] = ['required', 'string'];
        }

        $validator = Validator::make($inputs, $rules);

        return $validator->fails() ? $validator->errors()->all() : [];
    }

    private function afteSaveUser($user, $request)
    {
        if ($request->idApartament != 0 && $request->user()->rol_id == Rol::ADMIN) {
            Departament::find($request->idApartament)->update([
                'user_id' => $user->id,
            ]);
        }
    }

    public function getOwners(Request $request)
    {

        $owners = User::with(['units', 'rol'])
            ->where('rol_id', $request->rol)
            ->orderBy('name', 'asc')
            // ->where('id', '!=', $request->user()->id)
            ->get();

        return $this->returnSuccess(200, $owners);
    }

    public function getOwnersWithoutApartment()
    {
        $owners = User::with('rol')
            ->where('rol_id', Rol::PROPIETARIO)
            // ->whereDoesntHave('apartaments')
            ->orderBy('name', 'asc')
            ->get();

        return $this->returnSuccess(200, $owners);
    }

    public function getCountPendingsForAdmin()
    {
        $noticeTypeForAnnunce = 2;
        $waitStatus = [
            'reserve' => 2,
            'announces' => 1,
        ];
        $reservesPendings = Booking::where('status', $waitStatus['reserve'])->get();
        $announcesPendings = Notice::where('status', $waitStatus['announces'])
            ->where('type', $noticeTypeForAnnunce)->get();

        return $this->returnSuccess(200, [
            'reserves' => $reservesPendings,
            'announces' => $announcesPendings,
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
            'name' => ['required', 'regex:/^[a-zA-Z-À-ÿ .]+$/i'],
            'email' => ['required', 'email', 'unique:users'],
            'username' => ['required', 'unique:users', 'regex:/^[a-zA-Z-À-ÿ0-9 .]+$/i'],
            'password' => ['required', 'min:8'],

        ];
        $messages = [
            'name.required' => 'El nombre es requerido.',
            'name.regex' => 'Nombre no valido',
            'email.required' => 'El email es requerido.',
            'email.unique' => 'El email ya esta registrado.',
            'email.email' => 'Email no valido',
            'username.required' => 'Nombre de usuario es requerido',
            'username.unique' => 'Nombre de usuario ya esta registrado.',
            'username.regex' => 'Nombre de usuario no valido',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener un minimo de 8 caracteres',
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
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
            // throw $th;
        }

        return $this->returnSuccess(200, 'ok');
    }

    public function pruebaRealtimeNotification()
    {
        $user = User::find(3);
        try {
            $user->notify(new RealtimeNotification(
                title: 'Notificación de prueba',
                message: 'Esta es una notificación en tiempo real de prueba.',
                url: '/',
                meta: [
                    'icon' => 'eva-bell-outline',
                ]
            ));

            return $this->returnSuccess(200, 'Notificación enviada');
        } catch (Exception $e) {
            return $this->returnFail(500, $e->getMessage());
        }
    }

    public function setAvailableComunAreaToReserve(?PeoplesXDepartaments $people = null)
    {
        // TODO: Implementar lógica para habilitar áreas comunes a reservar para el usuario
    }

    /**
     * Completa la configuración de primer inicio de sesión.
     * Actualiza contraseña, teléfono y desactiva la bandera is_first_time.
     */
    public function completeFirstTime(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'unique:users,email'],

            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['required', 'string', 'min:7'],
        ], [
            'password.required' => 'La contraseña es requerida.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'phone.required' => 'El número de teléfono es requerido.',
            'phone.min' => 'El número de teléfono debe tener al menos 7 dígitos.',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $user = $request->user();
            $user->update([
                'password' => bcrypt($request->password),
                'phone' => $request->phone,
                'email' => $request->email,
                'is_first_time' => 0,
            ]);

            return $this->returnSuccess(200, 'Configuración completada exitosamente.');
        } catch (Exception $e) {
            Log::error('Error en completeFirstTime: '.$e->getMessage());

            return $this->returnFail(500, 'Error al actualizar los datos.');
        }
    }

    /**
     * Actualiza los datos de un residente/familiar/airbnb creado por el propietario autenticado.
     */
    public function updateResident(Request $request, int $id)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->returnFail(404, 'Usuario no encontrado');
        }

        // Verificar que el usuario autenticado es el creador de este residente
        $isCreator = PeoplesXDepartaments::where('user_id', $id)
            ->where('created_by', $request->user()->id)
            ->exists();

        if (! $isCreator) {
            return $this->returnFail(403, 'No tiene permiso para editar este usuario.');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$id],
            'phone' => ['nullable', 'string'],
            'parentesco' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'name.required' => 'El nombre es requerido.',
            'email.required' => 'El email es requerido.',
            'email.email' => 'El email no es válido.',
            'email.unique' => 'El email ya está registrado.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $data = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'parentesco' => $request->parentesco,
            ];

            if ($request->filled('password')) {
                $data['password'] = bcrypt($request->password);
            }

            $user->update($data);

            return $this->returnSuccess(200, 'Usuario actualizado con éxito.');
        } catch (Exception $e) {
            Log::error('Error en updateResident: '.$e->getMessage());

            return $this->returnFail(500, 'Error al actualizar el usuario.');
        }
    }

    /**
     * Obtiene las reservas de un residente creado por el propietario autenticado.
     */
    public function getResidentBookings(Request $request, int $userId)
    {
        // Verificar que el usuario autenticado es el creador de este residente
        $isCreator = PeoplesXDepartaments::where('user_id', $userId)
            ->where('created_by', $request->user()->id)
            ->exists();

        if (! $isCreator) {
            return $this->returnFail(403, 'No tiene permiso para ver las reservas de este usuario.');
        }

        $bookings = Booking::with('comunArea', 'pay')
            ->where('user_id', $userId)
            ->orderBy('date', 'desc')
            ->get();

        return $this->returnSuccess(200, $bookings);
    }

    public function destroy(int $id, Request $request)
    {
        $user = User::find($id);
        if (! $user) {
            return $this->returnFail(404, 'Usuario no encontrado');
        }

        if ($user->rol_id === Rol::ADMIN) {
            return $this->returnFail(400, 'No se puede eliminar un usuario administrador');
        }

        // Cancelar reservas pendientes (mantener el registro)
        Booking::where('user_id', $id)
            ->whereIn('status', [Booking::STATUS_PENDING_PAY, Booking::STATUS_PENDING_APPROVAL, Booking::STATUS_SUCCESS])
            ->update(['status' => Booking::STATUS_CANCELLED]);

        $user->delete();

        return $this->returnSuccess(200, 'Usuario eliminado con éxito');
    }
}
