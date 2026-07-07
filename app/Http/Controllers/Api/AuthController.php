<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
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
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', request()->username)->first();
        if (! $user || ! Hash::check(request()->password, $user->password)) {
            return $this->returnFail(505, 'Credenciales no validas');
        }
        if ($user->status != 1) {
            return $this->returnFail(505, 'Usuario Inactivo');
        }

        return $this->returnSuccess(
            200,
            ['token' => $user->createToken(request()->userAgent())->plainTextToken]
        );
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
            return $this->returnSuccess(200, [
                'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
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

        Mail::to($user->email)->send(new ResetPasswordMail($user, $token));

        return $this->returnSuccess(200, [
            'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }
}
