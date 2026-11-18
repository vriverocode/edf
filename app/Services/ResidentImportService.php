<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RegistrationStatus;
use App\Imports\ResidentsImport;
use App\Mail\ResidentWelcomeMail;
use App\Models\Departament;
use App\Models\Rol;
use App\Models\Urbanismo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

final class ResidentImportService
{
    /**
     * @return array{
     *     created: int,
     *     skipped: int,
     *     errors: array<int, array{fila: int, errores: array<int, string>}>,
     *     skipped_rows: array<int, array{fila: int, email: string, motivo: string}>,
     *     credentials: array<int, array{fila: int, nombre: string, email: string, username: string, password: string, unidad: string, email_enviado: bool}>
     * }
     */
    public function import(Urbanismo $urbanismo, UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        $this->assertRequiredColumns($rows->first());

        $created = 0;
        $errors = [];
        $skippedRows = [];
        $credentials = [];
        $candidates = [];

        foreach ($rows as $index => $row) {
            $data = $this->normalizeResidentRow($row);

            $validator = Validator::make($data, [
                'nombre' => ['required', 'string', 'max:255'],
                'apellido' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'telefono' => ['required', 'string', 'max:20'],
                'unidad' => ['required', 'string', 'max:255'],
                'dni' => ['nullable', 'string', 'max:10'],
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

        $existingEmails = $this->existingEmails(
            collect($candidates)->pluck('data.email')->all()
        );

        foreach ($candidates as $candidate) {
            $data = $candidate['data'];
            $email = Str::lower($data['email']);

            if (isset($existingEmails[$email])) {
                $skippedRows[] = [
                    'fila' => $candidate['fila'],
                    'email' => $email,
                    'motivo' => 'El correo ya está registrado.',
                ];

                continue;
            }

            try {
                $result = DB::transaction(function () use ($data, $email, $urbanismo) {
                    $username = UsernameGenerator::fromName($data['nombre'], $data['apellido']);
                    $password = UsernameGenerator::defaultPassword($username);
                    $unit = trim((string) $data['unidad']);

                    $user = User::create([
                        'name' => trim($data['nombre'].' '.$data['apellido']),
                        'email' => $email,
                        'username' => $username,
                        'phone' => $data['telefono'],
                        'dni' => $data['dni'] ?? null,
                        'password' => $password,
                        'rol_id' => Rol::PROPIETARIO,
                        'urbanismo_id' => $urbanismo->id,
                        'unidad' => $unit,
                        'estado_registro' => RegistrationStatus::Activo,
                        'is_first_time' => 1,
                        'status' => 1,
                    ]);

                    $user->forceFill(['email_verified_at' => now()])->save();

                    $this->resolveDepartment($urbanismo, $unit, $user);

                    return [
                        'user' => $user,
                        'username' => $username,
                        'password' => $password,
                    ];
                });
            } catch (Throwable $exception) {
                if (! $exception instanceof RuntimeException) {
                    report($exception);
                }

                $errors[] = [
                    'fila' => $candidate['fila'],
                    'errores' => [
                        $exception instanceof RuntimeException
                            ? $exception->getMessage()
                            : 'No se pudo crear el residente.',
                    ],
                ];

                continue;
            }

            $existingEmails[$email] = true;
            $created++;

            $emailSent = $this->sendWelcomeEmail($result['user'], $result['username'], $result['password']);

            $credentials[] = [
                'fila' => $candidate['fila'],
                'nombre' => $result['user']->name,
                'email' => $result['user']->email,
                'username' => $result['username'],
                'password' => $result['password'],
                'unidad' => $result['user']->unidad,
                'email_enviado' => $emailSent,
            ];
        }

        return [
            'created' => $created,
            'skipped' => count($skippedRows),
            'errors' => $errors,
            'skipped_rows' => $skippedRows,
            'credentials' => $credentials,
        ];
    }

    /**
     * @return Collection<int, Collection<string, mixed>>
     */
    private function readRows(UploadedFile $file): Collection
    {
        try {
            $rows = Excel::toCollection(new ResidentsImport, $file)->first() ?? collect();
        } catch (Throwable) {
            throw new RuntimeException('No se pudo leer el archivo. Verifica que sea un Excel o CSV válido.');
        }

        if ($rows->isEmpty()) {
            throw new RuntimeException('El archivo no contiene filas para procesar.');
        }

        return $rows;
    }

    private function assertRequiredColumns(Collection $firstRow): void
    {
        $columns = array_keys($firstRow->toArray());

        $required = [
            'nombre' => ['nombre', 'nombres', 'name', 'first_name'],
            'apellido' => ['apellido', 'apellidos', 'surname', 'last_name'],
            'email' => ['email', 'correo', 'correo_electronico'],
            'telefono' => ['telefono', 'phone', 'celular', 'movil'],
            'dpt/casa/predio' => [
                'unidad',
                'dpt',
                'dpto',
                'departamento',
                'apartamento',
                'casa',
                'predio',
                'lote',
                'dpt_casa_predio',
                'dptcasapredio',
                'departamento_casa_predio',
                'departamentocasapredio',
            ],
        ];

        $missing = [];

        foreach ($required as $label => $aliases) {
            if (array_intersect($aliases, $columns) === []) {
                $missing[] = $label;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'El Excel debe incluir las columnas: nombre, apellido, email, telefono y dpt/casa/predio. Faltan: '.implode(', ', $missing).'.'
            );
        }
    }

    /**
     * @return array{nombre: string, apellido: string, email: string, unidad: string, telefono: ?string, dni: ?string}
     */
    private function normalizeResidentRow(Collection $row): array
    {
        $data = $row->toArray();

        return [
            'nombre' => $this->firstFilled($data, ['nombre', 'nombres', 'name', 'first_name']) ?? '',
            'apellido' => $this->firstFilled($data, ['apellido', 'apellidos', 'surname', 'last_name']) ?? '',
            'email' => Str::lower(
                $this->firstFilled($data, ['email', 'correo', 'correo_electronico']) ?? ''
            ),
            'unidad' => $this->firstFilled($data, [
                'unidad',
                'dpt',
                'dpto',
                'departamento',
                'apartamento',
                'casa',
                'predio',
                'lote',
                'dpt_casa_predio',
                'dptcasapredio',
                'departamento_casa_predio',
                'departamentocasapredio',
            ]) ?? '',
            'telefono' => $this->firstFilled($data, ['telefono', 'phone', 'celular', 'movil']),
            'dni' => $this->firstFilled($data, ['dni', 'documento']),
        ];
    }

    private function resolveDepartment(Urbanismo $urbanismo, string $unit, User $user): Departament
    {
        $departments = Departament::with(['owner:id,urbanismo_id'])
            ->where('number', $unit)
            ->where('type', Departament::TYPE_DEPARTAMENTO)
            ->get();

        $ownedInUrbanismo = $departments->first(
            fn (Departament $department) => $department->user_id !== null
                && (int) $department->owner?->urbanismo_id === (int) $urbanismo->id
        );

        if ($ownedInUrbanismo) {
            throw new RuntimeException(
                'La unidad "'.$unit.'" ya está asignada a otro propietario.'
            );
        }

        $unowned = $departments->first(fn (Departament $department) => $department->user_id === null);

        if ($unowned) {
            $unowned->update(['user_id' => $user->id]);

            return $unowned;
        }

        if ($departments->isNotEmpty()) {
            throw new RuntimeException(
                'La unidad "'.$unit.'" ya está asignada a otro propietario.'
            );
        }

        return Departament::create([
            'number' => $unit,
            'type' => Departament::TYPE_DEPARTAMENTO,
            'description' => 'Ocupable',
            'area' => 0,
            'floor' => 'N/A',
            'user_id' => $user->id,
        ]);
    }

    private function sendWelcomeEmail(User $user, string $username, string $password): bool
    {
        try {
            Mail::to($user->email)->send(new ResidentWelcomeMail($user, $username, $password));

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @param  list<string>  $emails
     * @return array<string, bool>
     */
    private function existingEmails(array $emails): array
    {
        return User::withTrashed()
            ->whereIn('email', collect($emails)->map(fn (string $email) => Str::lower($email))->all())
            ->pluck('email')
            ->map(fn (string $email) => Str::lower($email))
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function firstFilled(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (filled($data[$key] ?? null)) {
                return $this->toText($data[$key]);
            }
        }

        return null;
    }

    private function toText(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return number_format($value, 0, '', '');
        }

        return trim((string) $value);
    }
}
