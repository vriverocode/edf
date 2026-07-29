<?php

namespace App\Console\Commands;

use App\Models\Departament;
use App\Models\Pay;
use App\Models\Quota;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCuotasMorosos extends Command
{
    protected $signature = 'import:cuotas-morosos';

    protected $description = 'Importa cuotas desde hoja "morosos" (amarillo=status3, melocoton=status1)';

    private array $skipped = [];

    private array $stats = [
        'processed' => 0,
        'departments_created' => 0,
        'users_found' => 0,
        'users_not_found' => 0,
        'quotas_paid' => 0,
        'quotas_pending' => 0,
        'pays_created' => 0,
    ];

    public function handle(): int
    {
        $jsonPath = storage_path('app/cuotas_morosos.json');

        if (!file_exists($jsonPath)) {
            $this->error("Archivo $jsonPath no encontrado.");
            return 1;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (empty($data)) {
            $this->error('JSON vacío.');
            return 1;
        }

        $this->info("Procesando " . count($data) . " registros morosos...\n");

        $bar = $this->output->createProgressBar(count($data));
        $bar->start();

        foreach ($data as $item) {
            $this->processRow($item);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->printSummary();
        $this->generateSkippedReport();

        return 0;
    }

    private function processRow(array $item): void
    {
        $this->stats['processed']++;

        $codigo = $item['codigo'];
        $propietario = $item['propietario'];
        $predio = $item['predio'];
        $quotas = $item['quotas'];

        $departamentData = $this->parsePredio($predio);
        if (!$departamentData) {
            $this->skipped[] = [
                'codigo' => $codigo,
                'propietario' => $propietario,
                'predio' => $predio,
                'motivo' => "Predio '$predio' no se pudo parsear",
            ];
            return;
        }

        $departament = Departament::where('number', $departamentData['number'])->first();
        if (!$departament) {
            $departament = Departament::create([
                'number' => $departamentData['number'],
                'type' => $departamentData['type'],
                'address' => null,
                'block' => null,
                'area' => 0,
                'floor' => 'N/A',
                'description' => $departamentData['description'],
                'participation_percentage' => 0,
            ]);
            $this->stats['departments_created']++;
            $this->line("  [creado] Departamento {$departamentData['number']}");
        }

        $user = $this->findUserByName($propietario);
        if (!$user) {
            $user = User::find($departament->user_id);
        }

        if (!$user) {
            $this->skipped[] = [
                'codigo' => $codigo,
                'propietario' => $propietario,
                'predio' => $predio,
                'motivo' => "Usuario no encontrado ni el departamento {$departament->number} tiene user_id",
            ];
            $this->stats['users_not_found']++;
            return;
        }

        $this->stats['users_found']++;

        if (!$departament->user_id) {
            $departament->update(['user_id' => $user->id]);
        }

        foreach ($quotas as $q) {
            $this->createQuota($departament, $user, $q);
        }
    }

    private function parsePredio(string $predio): ?array
    {
        $prefixMap = [
            'DEPA-' => ['prefix' => 'dpt-', 'type' => Departament::TYPE_DEPARTAMENTO, 'label' => 'Departamento'],
            'ESTA-' => ['prefix' => 'EST-', 'type' => Departament::TYPE_ESTACIONAMIENTO, 'label' => 'Estacionamiento'],
            'DEPO-' => ['prefix' => 'DPO-', 'type' => Departament::TYPE_DEPOSITO, 'label' => 'Deposito'],
        ];

        foreach ($prefixMap as $excelPrefix => $map) {
            if (str_starts_with(strtoupper($predio), $excelPrefix)) {
                $number = substr($predio, strlen($excelPrefix));
                $dbNumber = $map['prefix'] . $number;
                return [
                    'number' => $dbNumber,
                    'type' => $map['type'],
                    'description' => $map['label'] . ' ' . $dbNumber,
                ];
            }
        }

        return null;
    }

    private function findUserByName(string $name): ?User
    {
        $normalized = trim(mb_strtoupper($name));

        $user = User::where(DB::raw('TRIM(UPPER(name))'), $normalized)->first();
        if ($user) {
            return $user;
        }

        $parts = preg_split('/[\s\-]+/', $normalized);
        $parts = array_filter($parts);

        if (count($parts) >= 2) {
            $lastName = end($parts);
            $firstName = reset($parts);
            $user = User::where(DB::raw('TRIM(UPPER(name))'), 'LIKE', "%$lastName%")
                ->where(DB::raw('TRIM(UPPER(name))'), 'LIKE', "%$firstName%")
                ->first();
            if ($user) {
                return $user;
            }
        }

        if (count($parts) >= 3) {
            $secondLastName = $parts[count($parts) - 2];
            $user = User::where(DB::raw('TRIM(UPPER(name))'), 'LIKE', "%$secondLastName%")
                ->where(DB::raw('TRIM(UPPER(name))'), 'LIKE', "%$lastName%")
                ->first();
            if ($user) {
                return $user;
            }
        }

        foreach ($parts as $part) {
            if (mb_strlen($part) < 3) {
                continue;
            }
            $user = User::where(DB::raw('TRIM(UPPER(name))'), 'LIKE', "%$part%")->first();
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function createQuota(Departament $departament, User $user, array $q): void
    {
        $month = $q['month'];
        $dueDate = $q['due_date'];
        $year = Carbon::parse($dueDate)->year;
        $isPaid = $q['is_paid'];
        $amount = $q['amount'];

        $existingQuota = Quota::where('departament_id', $departament->id)
            ->where('month', $month)
            ->whereYear('due_date', $year)
            ->first();

        if ($existingQuota) {
            return;
        }

        $description = $month === 0
            ? 'Acumulado 2025 - ' . $departament->number
            : 'Cuota ' . $q['label'] . ' - ' . $departament->number;

        $quota = Quota::create([
            'departament_id' => $departament->id,
            'amount' => $amount,
            'month' => $month,
            'due_date' => $dueDate,
            'type' => 1,
            'status' => $isPaid ? 3 : 1,
            'description' => $description,
            'number' => null,
            'maintenance_amount' => $amount,
            'water_amount' => 0,
        ]);

        if ($isPaid) {
            $this->stats['quotas_paid']++;

            $pay = Pay::create([
                'user_id' => $user->id,
                'type' => 1,
                'amount' => $amount,
                'status' => 2,
                'pay_date' => $dueDate,
                'reference' => 'Importacion automatica - ' . $q['label'],
                'vaucher' => null,
                'pay_method' => 1,
                'pay_id' => '',
            ]);

            $this->stats['pays_created']++;
            $pay->quotas()->attach($quota->id);
        } else {
            $this->stats['quotas_pending']++;
        }
    }

    private function printSummary(): void
    {
        $this->table(
            ['Metrica', 'Valor'],
            [
                ['Registros procesados', $this->stats['processed']],
                ['Departamentos creados', $this->stats['departments_created']],
                ['Usuarios encontrados', $this->stats['users_found']],
                ['Usuarios NO encontrados', $this->stats['users_not_found']],
                ['Cuotas PAID (status=3)', $this->stats['quotas_paid']],
                ['Cuotas PEND (status=1)', $this->stats['quotas_pending']],
                ['Pagos creados', $this->stats['pays_created']],
            ]
        );
    }

    private function generateSkippedReport(): void
    {
        if (empty($this->skipped)) {
            $this->info('No hubo registros omitidos.');
            return;
        }

        $reportPath = storage_path('app/cuotas_morosos_omitidos.md');
        $lines = [];
        $lines[] = '# Reporte de registros omitidos - Morosos';
        $lines[] = '';
        $lines[] = "Generado: " . now()->format('d/m/Y H:i:s');
        $lines[] = '';
        $lines[] = "Total omitidos: " . count($this->skipped);
        $lines[] = '';
        $lines[] = '| Codigo | Propietario | Predio | Motivo |';
        $lines[] = '|--------|-------------|--------|--------|';

        foreach ($this->skipped as $s) {
            $lines[] = "| {$s['codigo']} | {$s['propietario']} | {$s['predio']} | {$s['motivo']} |";
        }

        file_put_contents($reportPath, implode("\n", $lines));

        $this->warn("\n" . count($this->skipped) . " registros omitidos. Reporte: $reportPath");
    }
}
