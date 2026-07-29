<?php

namespace App\Console\Commands;

use App\Models\Departament;
use App\Models\Pay;
use App\Models\Quota;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportCuotasFromExcel extends Command
{
    protected $signature = 'import:cuotas-from-excel
                            {--sheet=all : Hoja a procesar: all, al-dia, morosos}';

    protected $description = 'Importa cuotas desde Excel (hojas "al dia" y "morosos")';

    private array $skipped = [];

    private array $stats = [
        'processed' => 0,
        'departments_created' => 0,
        'users_found' => 0,
        'users_not_found' => 0,
        'quotas_paid' => 0,
        'quotas_pending' => 0,
        'pays_created' => 0,
        'omitted' => 0,
    ];

    public function handle(): int
    {
        $sheet = $this->option('sheet');

        if (!in_array($sheet, ['all', 'al-dia', 'morosos'])) {
            $this->error('Opcion --sheet invalida: use all, al-dia o morosos');
            return 1;
        }

        $jsonPathAlDia = storage_path('app/cuotas_al_dia.json');
        $jsonPathMorosos = storage_path('app/cuotas_morosos.json');

        $allData = [];

        if (in_array($sheet, ['all', 'al-dia'])) {
            if (!file_exists($jsonPathAlDia)) {
                $this->error("Archivo $jsonPathAlDia no encontrado. Generalo primero con el script Python.");
                return 1;
            }
            $dataAlDia = json_decode(file_get_contents($jsonPathAlDia), true);
            foreach ($dataAlDia as &$item) {
                $item['sheet'] = 'al dia';
            }
            unset($item);
            $allData = array_merge($allData, $dataAlDia);
        }

        if (in_array($sheet, ['all', 'morosos'])) {
            if (!file_exists($jsonPathMorosos)) {
                $this->error("Archivo $jsonPathMorosos no encontrado. Generalo primero con el script Python.");
                return 1;
            }
            $dataMorosos = json_decode(file_get_contents($jsonPathMorosos), true);
            foreach ($dataMorosos as &$item) {
                $item['sheet'] = 'morosos';
            }
            unset($item);
            $allData = array_merge($allData, $dataMorosos);
        }

        if (empty($allData)) {
            $this->warn('No se encontraron registros para procesar.');
            return 0;
        }

        $this->info('Procesando ' . count($allData) . " registros...\n");

        $bar = $this->output->createProgressBar(count($allData));
        $bar->start();

        foreach ($allData as $item) {
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
            $this->stats['omitted']++;
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
                'motivo' => "Usuario con nombre '$propietario' no encontrado ni el departamento {$departament->number} tiene user_id asignado",
            ];
            $this->stats['users_not_found']++;
            $this->stats['omitted']++;
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
        $isPaid = $q['is_paid'] ?? true;
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
                ['Cuotas PAID (status=3)', $this->stats['quotas_paid']],
                ['Cuotas PEND (status=1)', $this->stats['quotas_pending']],
                ['Pagos creados', $this->stats['pays_created']],
                ['Omitidos', $this->stats['omitted']],
            ]
        );
    }

    private function generateSkippedReport(): void
    {
        if (empty($this->skipped)) {
            $this->info('No hubo registros omitidos.');
            return;
        }

        $reportPath = storage_path('app/import-cuotas-report.md');
        $lines = [];
        $lines[] = '# Reporte de registros omitidos - Importacion Cuotas';
        $lines[] = '';
        $lines[] = 'Generado: ' . now()->format('d/m/Y H:i:s');
        $lines[] = '';
        $lines[] = 'Total omitidos: ' . count($this->skipped);
        $lines[] = '';
        $lines[] = '| Codigo | Propietario | Predio | Motivo |';
        $lines[] = '|--------|-------------|--------|--------|';

        foreach ($this->skipped as $s) {
            $escaped = array_map(function ($v) {
                return str_replace('|', '\\|', $v ?? '');
            }, $s);
            $lines[] = "| {$escaped['codigo']} | {$escaped['propietario']} | {$escaped['predio']} | {$escaped['motivo']} |";
        }

        file_put_contents($reportPath, implode("\n", $lines));
        $this->warn("\n" . count($this->skipped) . " registros omitidos. Reporte: $reportPath");
    }
}
