<?php

namespace App\Http\Controllers\Api;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\JoinCommunityRequest;
use App\Http\Requests\Auth\RegisterResidentRequest;
use App\Mail\ResetPasswordMail;
use App\Models\AccountActivationToken;
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
            if ($user->urbanismo_id === null && $user->estado_registro === RegistrationStatus::Pendiente) {
                return $this->returnFail(403, 'Completa tu registro ingresando el código de tu comunidad.');
            }

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
        $email = $request->string('email')->toString();
        $password = $request->string('password')->toString();

        $existing = User::withTrashed()->where('email', $email)->first();

        if ($existing) {
            $canResume = ! $existing->trashed()
                && $existing->urbanismo_id === null
                && $existing->estado_registro === RegistrationStatus::Pendiente
                && Hash::check($password, $existing->password);

            if (! $canResume) {
                return $this->returnFail(422, 'El correo ya está registrado.');
            }

            $user = $existing;
        } else {
            $user = DB::transaction(function () use ($request, $email) {
                return User::create([
                    'name' => $request->string('nombre')->toString(),
                    'email' => $email,
                    'username' => UsernameGenerator::fromEmail($email),
                    'password' => $request->string('password')->toString(),
                    'rol_id' => Rol::PROPIETARIO,
                    'estado_registro' => RegistrationStatus::Pendiente,
                    'status' => 4,
                ]);
            });
        }

        $plainToken = $this->issueRegistrationToken($user);

        return $this->returnSuccess(201, [
            'message' => 'Cuenta creada. Ahora ingresa el código de tu comunidad.',
            'registration_token' => $plainToken,
            'expires_at' => now()->addDay()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function joinCommunity(JoinCommunityRequest $request): JsonResponse
    {
        $outcome = DB::transaction(function () use ($request) {
            $token = AccountActivationToken::with('user')
                ->where('token_hash', hash('sha256', $request->string('registration_token')->toString()))
                ->where('purpose', 'registration')
                ->lockForUpdate()
                ->first();

            if (! $token || ! $token->isUsable()) {
                return ['status' => 422, 'error' => 'El registro expiró o no es válido. Vuelve a crear tu cuenta.'];
            }

            $user = $token->user;

            if ($user->urbanismo_id !== null || $user->estado_registro !== RegistrationStatus::Pendiente) {
                return ['status' => 422, 'error' => 'Tu cuenta ya tiene una comunidad asignada.'];
            }

            $urbanismo = Urbanismo::findByInvitationCode($request->string('codigo_invitacion')->toString());

            if (! $urbanismo || ! $urbanismo->activo) {
                return ['status' => 422, 'error' => 'Código de invitación inválido o urbanismo inactivo.'];
            }

            $user->forceFill([
                'urbanismo_id' => $urbanismo->id,
                'estado_registro' => RegistrationStatus::Cuarentena,
            ])->save();

            $token->forceFill(['used_at' => now()])->save();
            $user->activationTokens()->whereKeyNot($token->getKey())->delete();

            return ['urbanismo' => $urbanismo];
        });

        if (isset($outcome['error'])) {
            return $this->returnFail($outcome['status'], $outcome['error']);
        }

        return $this->returnSuccess(200, [
            'message' => 'Te uniste a la comunidad. Tu cuenta está pendiente de aprobación.',
            'estado_registro' => RegistrationStatus::Cuarentena->value,
            'urbanismo' => $outcome['urbanismo']->only(['id', 'nombre']),
        ]);
    }

    private function issueRegistrationToken(User $user): string
    {
        $user->activationTokens()->where('purpose', 'registration')->delete();

        $plainToken = Str::random(64);

        $user->activationTokens()->create([
            'purpose' => 'registration',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
        ]);

        return $plainToken;
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
