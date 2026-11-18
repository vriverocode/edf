<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\RegistrationStatus;
use App\Exports\ResidentsImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CompleteOnboardingRequest;
use App\Http\Requests\Onboarding\ImportResidentsRequest;
use App\Http\Requests\Onboarding\ValidateActivationTokenRequest;
use App\Models\AccountActivationToken;
use App\Models\Urbanismo;
use App\Models\User;
use App\Services\ResidentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

class OnboardingController extends Controller
{
    public function import(
        ImportResidentsRequest $request,
        Urbanismo $urbanismo,
        ResidentImportService $importService,
    ): JsonResponse {
        try {
            $summary = $importService->import($urbanismo, $request->file('file'));
        } catch (RuntimeException $exception) {
            return $this->returnFail(422, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->returnFail(500, 'No se pudo procesar el archivo de residentes.');
        }

        return $this->returnSuccess(200, $summary);
    }

    public function importTemplate(Urbanismo $urbanismo)
    {
        return Excel::download(
            new ResidentsImportTemplateExport,
            'plantilla-residentes.xlsx'
        );
    }

    public function validateActivation(ValidateActivationTokenRequest $request): JsonResponse
    {
        $token = $this->findUsableToken(
            $request->string('token')->toString(),
            $request->string('email')->toString(),
        );

        if (! $token) {
            return $this->returnFail(404, 'El enlace no es válido o ha expirado.');
        }

        $user = $token->user;

        return $this->returnSuccess(200, [
            'name' => $user->name,
            'email' => $user->email,
            'unidad' => $user->unidad,
            'urbanismo' => $user->urbanismo?->only(['id', 'nombre', 'logo_url']),
            'expires_at' => $token->expires_at?->toIso8601String(),
        ]);
    }

    public function complete(CompleteOnboardingRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $token = $this->findUsableToken(
                $request->string('token')->toString(),
                $request->string('email')->toString(),
                lock: true,
            );

            if (! $token) {
                return null;
            }

            $user = $token->user;

            $user->forceFill([
                'password' => $request->string('password')->toString(),
                'estado_registro' => RegistrationStatus::Activo,
                'email_verified_at' => now(),
            ])->save();

            $token->forceFill(['used_at' => now()])->save();

            $user->activationTokens()->whereKeyNot($token->getKey())->delete();

            return $user->fresh();
        });

        if (! $user) {
            return $this->returnFail(404, 'El enlace no es válido o ha expirado.');
        }

        return $this->returnSuccess(200, [
            'message' => 'Cuenta activada correctamente.',
            'token' => $user->createToken($request->userAgent() ?? 'account-activation')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'unidad' => $user->unidad,
                'urbanismo_id' => $user->urbanismo_id,
                'estado_registro' => $user->estado_registro->value,
            ],
        ]);
    }

    public function approve(Urbanismo $urbanismo, User $user): JsonResponse
    {
        if ($user->urbanismo_id !== $urbanismo->id) {
            return $this->returnFail(404, 'El usuario no pertenece a este urbanismo.');
        }

        if ($user->estado_registro !== RegistrationStatus::Cuarentena) {
            return $this->returnFail(422, 'El usuario no está en cuarentena.');
        }

        $user->forceFill([
            'estado_registro' => RegistrationStatus::Activo,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $this->returnSuccess(200, [
            'message' => 'Usuario aprobado correctamente.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'unidad' => $user->unidad,
                'estado_registro' => $user->estado_registro->value,
            ],
        ]);
    }

    private function findUsableToken(string $plainToken, string $email, bool $lock = false): ?AccountActivationToken
    {
        $query = AccountActivationToken::with('user.urbanismo')
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('purpose', 'activation');

        if ($lock) {
            $query->lockForUpdate();
        }

        $token = $query->first();

        if (! $token || ! $token->isUsable()) {
            return null;
        }

        if (strcasecmp($token->user->email, $email) !== 0) {
            return null;
        }

        return $token;
    }
}
