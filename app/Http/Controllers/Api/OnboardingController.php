<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CompleteOnboardingRequest;
use App\Http\Requests\Onboarding\ImportResidentsRequest;
use App\Http\Requests\Onboarding\ValidateActivationTokenRequest;
use App\Imports\ResidentsImport;
use App\Jobs\SendOnboardingMagicLinkJob;
use App\Models\AccountActivationToken;
use App\Models\Rol;
use App\Models\Urbanismo;
use App\Models\User;
use App\Services\UsernameGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class OnboardingController extends Controller
{
    public function import(ImportResidentsRequest $request, Urbanismo $urbanismo): JsonResponse
    {
        try {
            $rows = Excel::toCollection(new ResidentsImport, $request->file('file'))->first() ?? collect();
        } catch (Throwable) {
            return $this->returnFail(422, 'No se pudo leer el archivo. Verifica que sea un Excel o CSV válido.');
        }

        if ($rows->isEmpty()) {
            return $this->returnFail(422, 'El archivo no contiene filas para procesar.');
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        $candidates = [];

        foreach ($rows as $index => $row) {
            $data = $this->normalizeResidentRow($row);

            $validator = Validator::make($data, [
                'nombre' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'unidad' => ['required', 'string', 'max:255'],
                'dni' => ['nullable', 'string', 'max:10'],
                'telefono' => ['nullable', 'string', 'max:20'],
            ]);

            if ($validator->fails()) {
                $errors[] = [
                    'fila' => $index + 2,
                    'errores' => $validator->errors()->all(),
                ];

                continue;
            }

            $candidates[] = [
                'fila' => $index + 2,
                'data' => $validator->validated(),
            ];
        }

        $emails = collect($candidates)->pluck('data.email')->filter()->unique()->values()->all();

        $existingEmails = User::withTrashed()
            ->whereIn('email', $emails)
            ->pluck('email')
            ->map(fn (string $email) => Str::lower($email))
            ->flip()
            ->all();

        foreach ($candidates as $candidate) {
            $data = $candidate['data'];
            $email = Str::lower($data['email']);

            if (isset($existingEmails[$email])) {
                $skipped++;

                continue;
            }

            try {
                $user = DB::transaction(function () use ($data, $urbanismo, $email) {
                    return User::create([
                        'name' => $data['nombre'],
                        'email' => $email,
                        'username' => UsernameGenerator::fromEmail($email),
                        'phone' => $data['telefono'] ?? null,
                        'dni' => $data['dni'] ?? null,
                        'password' => Str::password(40),
                        'rol_id' => Rol::PROPIETARIO,
                        'urbanismo_id' => $urbanismo->id,
                        'unidad' => $data['unidad'],
                        'estado_registro' => RegistrationStatus::Pendiente,
                        'status' => 1,
                    ]);
                });

                $existingEmails[$email] = true;
                SendOnboardingMagicLinkJob::dispatch($user->id);
                $created++;
            } catch (Throwable $exception) {
                $errors[] = [
                    'fila' => $candidate['fila'],
                    'errores' => [$exception->getMessage()],
                ];
            }
        }

        return $this->returnSuccess(200, [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
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
            ->where('token_hash', hash('sha256', $plainToken));

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

    /**
     * @return array{nombre: string, email: string, unidad: string, dni: ?string, telefono: ?string}
     */
    private function normalizeResidentRow(Collection $row): array
    {
        $data = $row->toArray();

        $dni = $this->firstFilled($data, ['dni', 'documento']);
        $phone = $this->firstFilled($data, ['telefono', 'phone', 'celular']);

        return [
            'nombre' => $this->firstFilled($data, ['nombre', 'name', 'nombre_completo']) ?? '',
            'email' => Str::lower($this->firstFilled($data, ['email', 'correo', 'correo_electronico']) ?? ''),
            'unidad' => $this->firstFilled($data, ['unidad', 'apartamento', 'departamento']) ?? '',
            'dni' => $dni,
            'telefono' => $phone,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function firstFilled(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (filled($data[$key] ?? null)) {
                return trim((string) $data[$key]);
            }
        }

        return null;
    }
}
