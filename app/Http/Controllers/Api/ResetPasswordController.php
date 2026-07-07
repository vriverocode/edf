<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    public function validateToken(): JsonResponse
    {
        request()->validate([
            'token' => 'required|string',
        ]);

        $token = request()->token;

        $record = DB::table('password_reset_tokens')
            ->where('token', $token)
            ->first();

        if (! $record) {
            return $this->returnFail(404, 'Token no válido');
        }

        $expiresAt = now()->subMinutes(60);
        if (strtotime($record->created_at) < $expiresAt->timestamp) {
            DB::table('password_reset_tokens')
                ->where('email', $record->email)
                ->delete();

            return $this->returnFail(404, 'Token expirado');
        }

        return $this->returnSuccess(200, ['message' => 'Token válido']);
    }

    public function reset(): JsonResponse
    {
        request()->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $token = request()->token;
        $email = request()->email;
        $password = request()->password;

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $token)
            ->first();

        if (! $record) {
            return $this->returnFail(404, 'Token no válido');
        }

        $expiresAt = now()->subMinutes(60);
        if (strtotime($record->created_at) < $expiresAt->timestamp) {
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            return $this->returnFail(404, 'Token expirado');
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->returnFail(404, 'Usuario no encontrado');
        }

        $user->forceFill([
            'password' => Hash::make($password),
        ])->save();

        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        return $this->returnSuccess(200, ['message' => 'Contraseña actualizada correctamente']);
    }
}
