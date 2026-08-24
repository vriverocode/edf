<?php

namespace App\Console\Commands;

use App\Models\Departament;
use App\Models\Quota;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportCuotasActualizadasMorosidad extends Command
{
    protected $signature = 'import:cuotas-actualizadas-morosidad
                            {file? : Ruta al archivo .xlsm (por defecto referencias/cuotasActualizadas.xlsm)}';

    protected $description = 'Importa la hoja REPORTE DE MOROSIDAD de cuotasActualizadas.xlsm a la tabla quotas (positivos=status 1, negativos=status 3)';

    private const SHEET_NAME = 'REPORTE DE MOROSIDAD';

    private const FIRST_DATA_ROW = 5;

    private const LAST_DATA_ROW = 174;

    private array $monthColumns = [
        3 => ['month' => 0, 'year' => 2025, 'label' => 'Periodo 2025'],
        4 => ['month' => 1, 'year' => 2026, 'label' => 'Enero - 2026'],
        5 => ['month' => 2, 'year' => 2026, 'label' => 'Febrero - 2026'],
        6 => ['month' => 3, 'year' => 2026, 'label' => 'Marzo - 2026'],
        7 => ['month' => 4, 'year' => 2026, 'label' => 'Abril - 2026'],
        8 => ['month' => 5, 'year' => 2026, 'label' => 'Mayo - 2026'],
        9 => ['month' => 6, 'year' => 2026, 'label' => 'Junio - 2026'],
        10 => ['month' => 7, 'year' => 2026, 'label' => 'Julio - 2026'],
    ];

    private array $skipped = [];

    private array $stats = [
        'rows' => 0,
        'quotas_created' => 0,
        'quotas_duplicated' => 0,
        'departments_created' => 0,
        'departments_not_found' => 0,
        'users_found' => 0,
        'users_not_found' => 0,
        'status_1' => 0,
        'status_3' => 0,
        'omitted' => 0,
    ];

    public function handle(): int
    {
        $file = $this->argument('file') ?: base_path('referencias/cuotasActualizadas.xlsm');

        if (! file_exists($file)) {
            $this->error("Archivo no encontrado: $file");

            return 1;
        }

        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);

        $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME);
        if (! $sheet) {
            $this->error("Hoja '".self::SHEET_NAME."' no encontrada. Hojas disponibles: ".implode(', ', $spreadsheet->getSheetNames()));

            return 1;
        }

        $this->info("Procesando hoja '".self::SHEET_NAME."'...\n");

        $bar = $this->output->createProgressBar(self::LAST_DATA_ROW - self::FIRST_DATA_ROW + 1);
        $bar->start();

        for ($row = self::FIRST_DATA_ROW; $row <= self::LAST_DATA_ROW; $row++) {
            $this->processRow($sheet, $row);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->printSummary();
        $this->generateSkippedReport();

        return 0;
    }

    private function processRow(Worksheet $sheet, int $row): void
    {
        $this->stats['rows']++;

        $deptoCode = $this->cellValue($sheet, $row, 1);
        $propietario = trim((string) $this->cellValue($sheet, $row, 2));

        $departamentsData = $this->resolveDepartaments($deptoCode);

        if ($departamentsData === null) {
            $this->skip($deptoCode, $propietario, 'Codigo de predio no mapeable');

            return;
        }

        $user = $this->findUserByName($propietario);
        if ($user) {
            $this->stats['users_found']++;
        } else {
            $this->stats['users_not_found']++;
        }

        foreach ($departamentsData as $deptData) {
            $departament = Departament::where('number', $deptData['number'])->first();

            if (! $departament) {
                $departament = Departament::create([
                    'number' => $deptData['number'],
                    'type' => $deptData['type'],
                    'address' => null,
                    'block' => null,
                    'area' => 0,
                    'floor' => 'N/A',
                    'description' => $deptData['description'],
                    'participation_percentage' => 0,
                ]);
                $this->stats['departments_created']++;
                $this->line("  [creado] Departamento {$deptData['number']}");
            }

            if ($user && ! $departament->user_id) {
                $departament->update(['user_id' => $user->id]);
            }

            if (! $departament->user_id && ! $user) {
                $this->skip($deptoCode, $propietario, "Departamento {$departament->number} sin user_id y propietario no encontrado (cuotas importadas igualmente)");
                $this->stats['departments_not_found']++;
            }

            foreach ($this->monthColumns as $col => $meta) {
                $amount = $this->cellValue($sheet, $row, $col);

                if ($amount === null || $amount === '') {
                    continue;
                }

                $this->createQuota($departament, (float) $amount, $meta);
            }
        }
    }

    private function resolveDepartaments(mixed $code): ?array
    {
        if ($code === null || $code === '') {
            return null;
        }

        if (is_int($code) || (is_float($code) && floor($code) === $code) || (is_string($code) && ctype_digit(trim($code)))) {
            $number = 'dpt-'.(int) $code;

            return [['number' => $number, 'type' => Departament::TYPE_DEPARTAMENTO, 'description' => 'Departamento '.$number]];
        }

        $result = [];

        foreach (preg_split('/\r\n|\n|\r/', (string) $code) as $line) {
            $line = strtoupper(trim($line));
            if ($line === '') {
                continue;
            }

            $parsed = null;

            foreach ([
                'DEPA-' => ['prefix' => 'dpt-', 'type' => Departament::TYPE_DEPARTAMENTO, 'label' => 'Departamento'],
                'ESTA-' => ['prefix' => 'EST-', 'type' => Departament::TYPE_ESTACIONAMIENTO, 'label' => 'Estacionamiento'],
                'DEPO-' => ['prefix' => 'DPO-', 'type' => Departament::TYPE_DEPOSITO, 'label' => 'Deposito'],
            ] as $excelPrefix => $map) {
                if (str_starts_with($line, $excelPrefix)) {
                    $suffix = substr($line, strlen($excelPrefix));
                    if (! ctype_digit($suffix)) {
                        return null;
                    }
                    $number = $map['prefix'].$suffix;
                    $parsed = ['number' => $number, 'type' => $map['type'], 'description' => $map['label'].' '.$number];

                    break;
                }
            }

            if ($parsed === null) {
                return null;
            }

            $result[] = $parsed;
        }

        return empty($result) ? null : $result;
    }

    private function createQuota(Departament $departament, float $amount, array $meta): void
    {
        $month = $meta['month'];
        $year = $meta['year'];
        $dueDate = $this->dueDateFor($month, $year);

        $exists = Quota::where('departament_id', $departament->id)
            ->where('month', $month)
            ->whereYear('due_date', $year)
            ->exists();

        if ($exists) {
            $this->stats['quotas_duplicated']++;

            return;
        }

        $status = $amount < 0 ? 3 : 1;
        $description = 'Cuota '.$meta['label'].' - '.$departament->number;

        Quota::create([
            'departament_id' => $departament->id,
            'maintenance_amount' => $amount,
            'water_amount' => 0,
            'amount' => $amount,
            'month' => $month,
            'due_date' => $dueDate,
            'type' => $departament->type,
            'description' => $description,
            'status' => $status,
            'number' => null,
        ]);

        $this->stats['quotas_created']++;

        if ($status === 1) {
            $this->stats['status_1']++;
        } else {
            $this->stats['status_3']++;
        }
    }

    private function dueDateFor(int $month, int $year): string
    {
        if ($month === 0) {
            return $year.'-12-31';
        }

        return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
    }

    private function cellValue(Worksheet $sheet, int $row, int $col): mixed
    {
        return $sheet->getCell([$col, $row])->getValue();
    }

    private function findUserByName(string $name): ?User
    {
        if ($name === '' || mb_strtoupper($name) === 'SIN IDENTIFICAR') {
            return null;
        }

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

    private function skip(mixed $deptoCode, string $propietario, string $motivo): void
    {
        $this->stats['omitted']++;
        $this->skipped[] = [
            'depto' => (string) $deptoCode,
            'propietario' => $propietario,
            'motivo' => $motivo,
        ];
    }

    private function printSummary(): void
    {
        $this->table(
            ['Metrica', 'Valor'],
            [
                ['Filas procesadas', $this->stats['rows']],
                ['Departamentos creados', $this->stats['departments_created']],
                ['Departamentos sin user_id', $this->stats['departments_not_found']],
                ['Propietarios encontrados', $this->stats['users_found']],
                ['Propietarios NO encontrados', $this->stats['users_not_found']],
                ['Cuotas creadas', $this->stats['quotas_created']],
                ['  - status 1 (Pago pendiente)', $this->stats['status_1']],
                ['  - status 3 (Pagada)', $this->stats['status_3']],
                ['Cuotas omitidas por duplicado', $this->stats['quotas_duplicated']],
                ['Filas omitidas', $this->stats['omitted']],
            ]
        );
    }

    private function generateSkippedReport(): void
    {
        $reportPath = storage_path('app/import-cuotas-actualizadas-morosidad.md');
        $lines = [];
        $lines[] = '# Reporte de omitidos - cuotasActualizadas (REPORTE DE MOROSIDAD)';
        $lines[] = '';
        $lines[] = 'Generado: '.now()->format('d/m/Y H:i:s');
        $lines[] = '';
        $lines[] = 'Total omitidos: '.count($this->skipped);
        $lines[] = '';
        $lines[] = '| Departamento | Propietario | Motivo |';
        $lines[] = '|--------------|-------------|--------|';

        foreach ($this->skipped as $s) {
            $escaped = array_map(function ($v) {
                return str_replace('|', '\\|', $v ?? '');
            }, $s);
            $lines[] = "| {$escaped['depto']} | {$escaped['propietario']} | {$escaped['motivo']} |";
        }

        file_put_contents($reportPath, implode("\n", $lines));
        $this->warn("\n".count($this->skipped)." filas omitidas. Reporte: $reportPath");
    }
}
