<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login()
    {
        request()->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', request()->username)->first();
        if (! $user || !Hash::check(request()->password, $user->password)) {
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
}
