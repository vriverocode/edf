<?php

namespace App\Console\Commands;

use App\Models\Departament;
use App\Models\Quota;
use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ValidateCuotasAgosto extends Command
{
    protected $signature = 'validate:cuotas-agosto
                            {file? : Ruta al archivo .xlsm (por defecto referencias/pagos_agosto.xlsm)}';

    protected $description = 'Valida cuotas en BD contra el Excel de pagos_agosto (ABONOS EFECTUADOS A AGO26)';

    private const SHEET_NAME = 'ABONOS EFECTUADOS A AGO26';

    private const FIRST_DATA_ROW = 5;

    private const LAST_DATA_ROW = 847;

    private const TOLERANCIA = 0.01;

    private array $accumulatedSaldo = [];

    private array $excelEntries = [];

    private array $discrepancies = [];

    private array $allComparisons = [];

    private int $exactMatches = 0;

    private int $amountMismatches = 0;

    private int $missingInBD = 0;

    private int $missingInExcel = 0;

    private int $totalCompared = 0;

    public function handle(): int
    {
        $file = $this->argument('file') ?: base_path('referencias/pagos_agosto.xlsm');

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

        $this->info('Paso 1/3: Pre-escaneando saldos del Excel...');
        $this->preScanAccumulate($sheet);

        $this->info('Paso 2/3: Leyendo cuotas de BD...');
        $bdQuotas = $this->loadBDQuotas();
        $this->info('Cuotas en BD (year=2026, month>0): '.count($bdQuotas));

        $this->info('Paso 3/3: Comparando...');
        $this->compare($bdQuotas);

        $this->printSummary();
        $this->printDiscrepancies();
        $this->printFullDetail();

        $spreadsheet->disconnectWorksheets();

        return 0;
    }

    private function preScanAccumulate(Worksheet $sheet): void
    {
        for ($row = self::FIRST_DATA_ROW; $row <= self::LAST_DATA_ROW; $row++) {
            $predio = $this->cellValue($sheet, $row, 3);
            $periodo = $this->cellValue($sheet, $row, 6);
            $saldo = $this->cellValue($sheet, $row, 9);

            if ($predio === null || $predio === '' || ! is_numeric($saldo)) {
                continue;
            }

            $periodMeta = $this->parsePeriodo($periodo);
            if ($periodMeta === null || $periodMeta['month'] === 0) {
                continue;
            }

            $departamentsData = $this->resolveDepartaments($predio);
            if ($departamentsData === null) {
                continue;
            }

            $saldoPerDept = (float) $saldo / count($departamentsData);

            foreach ($departamentsData as $deptData) {
                $departament = Departament::where('number', $deptData['number'])->first();
                if (! $departament) {
                    continue;
                }

                $key = $departament->id.'|'.$periodMeta['month'].'|'.$periodMeta['year'];
                $this->accumulatedSaldo[$key] = ($this->accumulatedSaldo[$key] ?? 0) + $saldoPerDept;

                if (! isset($this->excelEntries[$key])) {
                    $this->excelEntries[$key] = [
                        'departament_id' => $departament->id,
                        'departament_number' => $departament->number,
                        'month' => $periodMeta['month'],
                        'year' => $periodMeta['year'],
                        'label' => $periodMeta['label'],
                        'saldo' => 0,
                    ];
                }
                $this->excelEntries[$key]['saldo'] = $this->accumulatedSaldo[$key];
            }
        }

        $this->info('Registros Excel validos: '.count($this->excelEntries));
    }

    private function loadBDQuotas(): array
    {
        $quotas = Quota::where('year', 2026)
            ->where('month', '>', 0)
            ->with('departament')
            ->get();

        $result = [];
        foreach ($quotas as $quota) {
            if (! $quota->departament) {
                continue;
            }
            $key = $quota->departament_id.'|'.$quota->month.'|'.$quota->year;
            $result[$key] = $quota;
        }

        return $result;
    }

    private function compare(array $bdQuotas): void
    {
        $allKeys = array_unique(array_merge(array_keys($this->excelEntries), array_keys($bdQuotas)));
        sort($allKeys);

        foreach ($allKeys as $key) {
            $excel = $this->excelEntries[$key] ?? null;
            $bd = $bdQuotas[$key] ?? null;

            if ($excel && $bd) {
                $this->totalCompared++;
                $diff = abs($excel['saldo'] - (float) $bd->amount);

                $record = [
                    'departament_number' => $excel['departament_number'] ?? $bd->departament->number,
                    'month' => $excel ? $excel['month'] : $bd->month,
                    'year' => $excel ? $excel['year'] : $bd->year,
                    'label' => $excel ? $excel['label'] : $this->monthName($bd->month).' - '.$bd->year,
                    'excel_amount' => $excel['saldo'],
                    'bd_amount' => (float) $bd->amount,
                    'difference' => $excel['saldo'] - (float) $bd->amount,
                    'status' => 'OK',
                ];

                if ($diff <= self::TOLERANCIA) {
                    $this->exactMatches++;
                    $record['status'] = 'OK';
                } else {
                    $this->amountMismatches++;
                    $record['status'] = 'DIFERENCIA';
                    $this->discrepancies[] = $record;
                }

                $this->allComparisons[] = $record;
            } elseif ($excel && ! $bd) {
                $this->missingInBD++;
                $this->allComparisons[] = [
                    'departament_number' => $excel['departament_number'],
                    'month' => $excel['month'],
                    'year' => $excel['year'],
                    'label' => $excel['label'],
                    'excel_amount' => $excel['saldo'],
                    'bd_amount' => null,
                    'difference' => null,
                    'status' => 'SIN EN BD',
                ];
            } elseif (! $excel && $bd) {
                $this->missingInExcel++;
                $this->allComparisons[] = [
                    'departament_number' => $bd->departament->number,
                    'month' => $bd->month,
                    'year' => $bd->year,
                    'label' => $this->monthName($bd->month).' - '.$bd->year,
                    'excel_amount' => null,
                    'bd_amount' => (float) $bd->amount,
                    'difference' => null,
                    'status' => 'SIN EN EXCEL',
                ];
            }
        }
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║         RESUMEN DE VALIDACION               ║');
        $this->info('╚══════════════════════════════════════════════╝');

        $this->table(
            ['Metrica', 'Valor'],
            [
                ['Total comparados', $this->totalCompared],
                ['Coincidencias exactas (±$0.01)', $this->exactMatches],
                ['Discrepancias de monto', $this->amountMismatches],
                ['Cuotas en BD sin en Excel', $this->missingInBD],
                ['Cuotas en Excel sin en BD', $this->missingInExcel],
            ]
        );
    }

    private function printDiscrepancies(): void
    {
        if (empty($this->discrepancies)) {
            $this->info('No se encontraron discrepancias de monto.');

            return;
        }

        $this->newLine();
        $this->warn('╔═══════════════════════════════════════════════════════════════════╗');
        $this->warn('║              DISCREPANCIAS DE MONTO                              ║');
        $this->warn('╚═══════════════════════════════════════════════════════════════════╝');

        $rows = [];
        foreach ($this->discrepancies as $d) {
            $rows[] = [
                $d['departament_number'],
                $d['label'],
                number_format($d['excel_amount'], 2),
                number_format($d['bd_amount'], 2),
                number_format($d['difference'], 2),
            ];
        }

        $this->table(
            ['Departamento', 'Periodo', 'Monto Excel', 'Monto BD', 'Diferencia'],
            $rows
        );
    }

    private function printFullDetail(): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════════════════════╗');
        $this->info('║                     DETALLE COMPLETO                                       ║');
        $this->info('╚══════════════════════════════════════════════════════════════════════════════╝');

        $rows = [];
        $num = 1;
        foreach ($this->allComparisons as $c) {
            $icon = match ($c['status']) {
                'OK' => '✓',
                'DIFERENCIA' => '✗',
                'SIN EN BD' => '?',
                'SIN EN EXCEL' => '!',
                default => '?',
            };

            $rows[] = [
                $num++,
                $c['departament_number'],
                $c['label'],
                $c['excel_amount'] !== null ? number_format($c['excel_amount'], 2) : '-',
                $c['bd_amount'] !== null ? number_format($c['bd_amount'], 2) : '-',
                $c['difference'] !== null ? number_format($c['difference'], 2) : '-',
                $icon.' '.$c['status'],
            ];
        }

        $this->table(
            ['#', 'Departamento', 'Periodo', 'Excel', 'BD', 'Dif', 'Estado'],
            $rows
        );
    }

    private function parsePeriodo(mixed $periodo): ?array
    {
        if ($periodo === null || $periodo === '') {
            return null;
        }

        if (is_string($periodo) && str_contains($periodo, 'Periodo')) {
            preg_match('/(\d{4})/', $periodo, $m);
            if (! $m) {
                return null;
            }
            $year = (int) $m[1];

            return ['month' => 0, 'year' => $year, 'label' => 'Periodo '.$year];
        }

        if ($periodo instanceof Carbon) {
            $month = (int) $periodo->format('m');
            $year = (int) $periodo->format('Y');

            return ['month' => $month, 'year' => $year, 'label' => $this->monthName($month).' - '.$year];
        }

        if (is_numeric($periodo)) {
            try {
                $date = Carbon::instance(Date::excelToDateTimeObject((float) $periodo));
                $month = (int) $date->format('m');
                $year = (int) $date->format('Y');

                return ['month' => $month, 'year' => $year, 'label' => $this->monthName($month).' - '.$year];
            } catch (\Exception $e) {
                return null;
            }
        }

        if (is_string($periodo)) {
            try {
                $date = Carbon::parse($periodo);
                $month = (int) $date->format('m');
                $year = (int) $date->format('Y');

                return ['month' => $month, 'year' => $year, 'label' => $this->monthName($month).' - '.$year];
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    private function monthName(int $month): string
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $months[$month] ?? '';
    }

    private function resolveDepartaments(mixed $code): ?array
    {
        if ($code === null || $code === '') {
            return null;
        }

        if (is_int($code) || (is_float($code) && floor($code) === $code) || (is_string($code) && ctype_digit(trim($code)))) {
            $number = 'DPT-'.(int) $code;

            return [['number' => $number, 'type' => Departament::TYPE_DEPARTAMENTO]];
        }

        $codeStr = (string) $code;

        $lines = preg_split('/\r\n|\n|\r/', $codeStr);
        if (count($lines) > 1) {
            $result = [];
            foreach ($lines as $line) {
                $parsed = $this->parseSinglePredio(trim($line));
                if ($parsed === null) {
                    return null;
                }
                $result[] = $parsed;
            }

            return empty($result) ? null : $result;
        }

        $parsed = $this->parseSinglePredio($codeStr);

        return $parsed === null ? null : [$parsed];
    }

    private function parseSinglePredio(string $predio): ?array
    {
        $predio = strtoupper(trim($predio));

        if ($predio === '' || $predio === 'S/ID' || $predio === 'ESTA-S/ID') {
            return null;
        }

        if (str_starts_with($predio, 'DEPA-')) {
            $suffix = substr($predio, 5);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'DPT-'.$suffix, 'type' => Departament::TYPE_DEPARTAMENTO];
        }

        if (str_starts_with($predio, 'ESTA-')) {
            $suffix = substr($predio, 5);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'EST-'.$suffix, 'type' => Departament::TYPE_ESTACIONAMIENTO];
        }

        if (str_starts_with($predio, 'DEPO-')) {
            $suffix = substr($predio, 5);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'DPO-'.$suffix, 'type' => Departament::TYPE_DEPOSITO];
        }

        if (str_starts_with($predio, 'LAV-')) {
            $suffix = substr($predio, 4);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'LAV-'.$suffix, 'type' => Departament::TYPE_LAV];
        }

        return null;
    }

    private function cellValue(Worksheet $sheet, int $row, int $col): mixed
    {
        return $sheet->getCell([$col, $row])->getValue();
    }
}
