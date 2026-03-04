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
    private function afteSaveUser($user, $request){
        if ($request->idApartament != 0 && $request->user()->rol_id == Rol::ADMIN) {
            Departament::find($request->idApartament)->update([
                'user_id' => $user->id
            ]);
        }

        if($request->user()->rol_id == Rol::PROPIETARIO){
            PeoplesXDepartaments::create([
                'user_id' => $user->id,
                'departament_id' => $request->idApartament,
                'type' => $request->idRol == Rol::INQUILINO ? Rol::INQUILINO : Rol::AIRBNB,
                'created_by' => $request->user()->id
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
}
