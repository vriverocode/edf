<?php

namespace App\Http\Controllers\Api;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterResidentRequest;
use App\Mail\ResetPasswordMail;
use App\Models\Rol;
use App\Models\Urbanismo;
use App\Models\User;
use App\Services\UsernameGenerator;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login()
    {
        request()->validate([
            'username' => 'required|string',
            'password' => 'required',
        ]);

        $login = trim((string) request()->username);

        $user = User::where('username', $login)->first()
            ?? User::where('email', $login)->first();
        if (! $user || ! Hash::check(request()->password, $user->password)) {
            return $this->returnFail(505, 'Credenciales no validas');
        }
        if ($user->status == 3) {
            return $this->returnFail(505, 'Usuario Inactivo');
        }

        if ($user->estado_registro !== RegistrationStatus::Activo) {
            return $this->returnFail(403, match ($user->estado_registro) {
                RegistrationStatus::Pendiente => 'Debes activar tu cuenta desde el enlace enviado a tu correo.',
                RegistrationStatus::Cuarentena => 'Tu registro está pendiente de aprobación por la administración.',
                RegistrationStatus::Suspendido => 'Tu cuenta está suspendida.',
                default => 'Cuenta no habilitada.',
            });
        }

        if ($user->urbanismo_id !== null && $user->urbanismo?->activo === false) {
            return $this->returnFail(403, 'El urbanismo se encuentra inactivo.');
        }

        return $this->returnSuccess(
            200,
            ['token' => $user->createToken(request()->userAgent())->plainTextToken]
        );
    }

    public function register(RegisterResidentRequest $request): JsonResponse
    {
        $urbanismo = Urbanismo::findByInvitationCode($request->string('codigo_invitacion')->toString());

        if (! $urbanismo || ! $urbanismo->activo) {
            return $this->returnFail(422, 'Código de invitación inválido o urbanismo inactivo.');
        }

        $email = $request->string('email')->toString();

        $user = DB::transaction(function () use ($request, $urbanismo, $email) {
            return User::create([
                'name' => $request->string('nombre')->toString(),
                'email' => $email,
                'username' => UsernameGenerator::fromEmail($email),
                'password' => $request->string('password')->toString(),
                'unidad' => $request->string('unidad')->toString(),
                'urbanismo_id' => $urbanismo->id,
                'rol_id' => Rol::PROPIETARIO,
                'estado_registro' => RegistrationStatus::Cuarentena,
                'status' => 1,
            ]);
        });

        return $this->returnSuccess(201, [
            'message' => 'Registro exitoso. Tu cuenta está a la espera de aprobación de la administración.',
            'estado_registro' => RegistrationStatus::Cuarentena->value,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'unidad' => $user->unidad,
            ],
        ]);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();

        return $this->returnSuccess(200, 'Sesión cerrada');
    }

    public function forgotPassword(): JsonResponse
    {
        request()->validate([
            'email' => 'required|email',
        ]);

        $email = request()->email;

        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->returnSuccess(400, [
                'message' => 'Correo no registrado.',
            ]);
        }

        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => now(),
        ]);

        try {
            Mail::to($user->email)->send(new ResetPasswordMail($user, $token));
        } catch (Exception $e) {
            return $this->returnFail(500, $e->getMessage());
        }

        return $this->returnSuccess(200, [
            'message' => 'Correo enviado, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }
}
