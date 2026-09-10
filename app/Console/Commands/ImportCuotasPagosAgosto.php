<?php

namespace App\Console\Commands;

use App\Models\Departament;
use App\Models\Pay;
use App\Models\Quota;
use App\Models\WaterReading;
use App\Services\MonthlyQuotaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportCuotasPagosAgosto extends Command
{
    protected $signature = 'import:cuotas-pagos-agosto
                            {file? : Ruta al archivo .xlsm (por defecto referencias/pagos_agosto.xlsm)}';

    protected $description = 'Importa cuotas y pagos desde la hoja ABONOS EFECTUADOS A AGO26 de pagos_agosto.xlsm';

    private const SHEET_NAME = 'ABONOS EFECTUADOS A AGO26';

    private const FIRST_DATA_ROW = 5;

    private const LAST_DATA_ROW = 847;

    private array $skipped = [];

    private array $stats = [
        'rows' => 0,
        'quotas_created' => 0,
        'quotas_duplicated' => 0,
        'pays_created' => 0,
        'pays_duplicated' => 0,
        'departments_not_found' => 0,
        'departments_skipped' => 0,
        'water_readings_linked' => 0,
        'user_not_found' => 0,
        'quotas_not_found_for_pay' => 0,
        'rows_with_abono' => 0,
    ];

    private array $accumulatedSaldo = [];

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

        $this->info('Pre-escaneando saldos por depto/mes/año...');
        $this->preScanAccumulate($sheet);

        // PASO 1: Crear cuotas
        $this->info("\nPaso 1/3: Creando cuotas...");
        $totalRows = self::LAST_DATA_ROW - self::FIRST_DATA_ROW + 1;
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        for ($row = self::FIRST_DATA_ROW; $row <= self::LAST_DATA_ROW; $row++) {
            $this->processRowQuotas($sheet, $row);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // PASO 2: Crear pagos (solo filas con abono > 0)
        $this->info('Paso 2/3: Creando pagos...');
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        for ($row = self::FIRST_DATA_ROW; $row <= self::LAST_DATA_ROW; $row++) {
            $this->processRowPays($sheet, $row);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // PASO 3: Sincronizar status de cuotas con pagos
        $this->info('Paso 3/3: Actualizando status de cuotas pagadas...');
        $quotaIdsWithPays = DB::table('pay_quota')->distinct()->pluck('quota_id');
        $updated = Quota::whereIn('id', $quotaIdsWithPays)->where('status', '!=', 3)->update(['status' => 3]);
        $this->info("Cuotas actualizadas a status 3 (Pagada): {$updated}");

        $this->printSummary();
        $this->generateSkippedReport();

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

            // Dividir el saldo entre los departamentos del código multi-línea
            $saldoPerDept = (float) $saldo / count($departamentsData);

            foreach ($departamentsData as $deptData) {
                $departament = Departament::where('number', $deptData['number'])->first();
                if (! $departament) {
                    continue;
                }

                $key = $departament->id.'|'.$periodMeta['month'].'|'.$periodMeta['year'];
                $this->accumulatedSaldo[$key] = ($this->accumulatedSaldo[$key] ?? 0) + $saldoPerDept;
            }
        }

        $this->info('Departamentos con saldo acumulado: '.count($this->accumulatedSaldo));
    }

    private function processRowQuotas(Worksheet $sheet, int $row): void
    {
        $this->stats['rows']++;

        $predio = $this->cellValue($sheet, $row, 3); // Columna C
        $periodo = $this->cellValue($sheet, $row, 6); // Columna F
        $saldo = $this->cellValue($sheet, $row, 9);   // Columna I

        // Parsear periodo
        $periodMeta = $this->parsePeriodo($periodo);
        if ($periodMeta === null) {
            $this->skip($predio, 'Periodo no valido: '.((string) $periodo ?: 'vacio'));

            return;
        }

        // Parsear saldo
        if ($saldo === null || $saldo === '' || ! is_numeric($saldo)) {
            $this->skip($predio, 'Saldo no valido');

            return;
        }

        // Resolver departamentos desde el codigo Predio
        $departamentsData = $this->resolveDepartaments($predio);
        if ($departamentsData === null) {
            $this->skip($predio, 'Codigo de predio no mapeable');

            return;
        }

        $month = $periodMeta['month'];
        $year = $periodMeta['year'];

        foreach ($departamentsData as $deptData) {
            $departament = Departament::where('number', $deptData['number'])->first();

            if (! $departament) {
                $this->stats['departments_not_found']++;
                $this->skip($predio, "Departamento {$deptData['number']} no encontrado en BD");

                continue;
            }

            // Verificar si la cuota ya existe
            $existingQuota = Quota::where('departament_id', $departament->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if ($existingQuota) {
                $this->stats['quotas_duplicated']++;

                continue;
            }

            $dueDate = $this->dueDateFor($month, $year);

            // Buscar lectura de agua existente
            $waterReading = WaterReading::where('departament_id', $departament->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            // Obtener saldo acumulado de todas las filas del mismo depto/mes/año
            $key = $departament->id.'|'.$month.'|'.$year;
            $accumulatedSaldo = $this->accumulatedSaldo[$key] ?? (float) $saldo;

            $waterAmount = $waterReading ? (float) $waterReading->amount : 0;
            $maintenanceAmount = $accumulatedSaldo - $waterAmount;

            if ($maintenanceAmount < 0) {
                $maintenanceAmount = 0;
            }

            // Crear cuota con saldo acumulado
            Quota::create([
                'departament_id' => $departament->id,
                'peoples_x_departments_id' => MonthlyQuotaService::findActiveTenantPivotId($departament->id),
                'water_reading_id' => $waterReading?->id,
                'maintenance_amount' => $maintenanceAmount,
                'water_amount' => $waterAmount,
                'amount' => $accumulatedSaldo,
                'number' => null,
                'month' => $month,
                'year' => $year,
                'due_date' => $dueDate,
                'type' => 1,
                'description' => 'Cuota '.$periodMeta['label'].' - '.$departament->number,
                'status' => 1,
            ]);

            $this->stats['quotas_created']++;

            if ($waterReading) {
                $this->stats['water_readings_linked']++;
            }
        }
    }

    private function processRowPays(Worksheet $sheet, int $row): void
    {
        $predio = $this->cellValue($sheet, $row, 3); // Columna C
        $periodo = $this->cellValue($sheet, $row, 6); // Columna F
        $abono = $this->cellValue($sheet, $row, 10);  // Columna J
        $comprobante = $this->cellValue($sheet, $row, 11); // Columna K
        $fechaPago = $this->cellValue($sheet, $row, 12);   // Columna L

        // Solo procesar filas con abono > 0
        $abonoVal = ($abono !== null && $abono !== '' && is_numeric($abono)) ? (float) $abono : 0;
        if ($abonoVal <= 0) {
            return;
        }

        $this->stats['rows_with_abono']++;

        // Parsear periodo
        $periodMeta = $this->parsePeriodo($periodo);
        if ($periodMeta === null) {
            $this->skip($predio, 'Periodo no valido para pago: '.((string) $periodo ?: 'vacio'));

            return;
        }

        // Resolver departamentos desde el codigo Predio
        $departamentsData = $this->resolveDepartaments($predio);
        if ($departamentsData === null) {
            $this->skip($predio, 'Codigo de predio no mapeable para pago');

            return;
        }

        // Dividir abono entre departamentos del código multi-línea
        $abonoPerDept = $abonoVal / count($departamentsData);

        $month = $periodMeta['month'];
        $year = $periodMeta['year'];

        foreach ($departamentsData as $deptData) {
            $departament = Departament::where('number', $deptData['number'])->first();

            if (! $departament) {
                $this->skip($predio, "Departamento {$deptData['number']} no encontrado en BD para pago");

                continue;
            }

            // Buscar la cuota existente
            $quota = Quota::where('departament_id', $departament->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if (! $quota) {
                $this->stats['quotas_not_found_for_pay']++;
                $this->skip($predio, "Cuota no encontrada para {$deptData['number']} {$month}/{$year}, pago no creado");

                continue;
            }

            $this->createPay($departament, $abonoPerDept, $fechaPago, $comprobante, $quota, $predio);
        }
    }

    private function createPay(Departament $departament, float $abonoVal, mixed $fechaPago, mixed $comprobante, Quota $quota, mixed $predio): void
    {
        $userId = $departament->user_id;

        if (! $userId) {
            $this->stats['user_not_found']++;
            $this->skip($predio, "Departamento {$departament->number} sin user_id, pago no creado");

            return;
        }

        // Parsear fecha de pago con manejo de errores
        $payDate = now()->toDateString();
        if ($fechaPago instanceof Carbon) {
            $payDate = $fechaPago->toDateString();
        } elseif ($fechaPago !== null && $fechaPago !== '') {
            try {
                // Serial de Excel (ej: 46253 = 2026-08-19)
                if (is_numeric($fechaPago)) {
                    $payDate = Carbon::instance(Date::excelToDateTimeObject((float) $fechaPago))->toDateString();
                } else {
                    $payDate = Carbon::parse($fechaPago)->toDateString();
                }
            } catch (\Exception $e) {
                $this->skip($predio, 'Fecha de pago invalida: '.(string) $fechaPago.', usando fecha actual');
            }
        }

        $reference = ($comprobante !== null && $comprobante !== '')
            ? (string) $comprobante
            : 'Importacion pagos agosto';

        // NIVEL 1: Verificar si YA EXISTE un pago vinculado a esta cuota (via pivote o quota_id directo)
        $alreadyLinked = Pay::where('quota_id', $quota->id)->exists()
            || Pay::whereHas('quotas', function ($q) use ($quota) {
                $q->where('quotas.id', $quota->id);
            })->exists();

        if ($alreadyLinked) {
            $this->stats['pays_duplicated']++;

            return;
        }

        // NIVEL 2: Verificar si existe pago identical (user + monto + fecha)
        $exactDuplicate = Pay::where('user_id', $userId)
            ->where('amount', $abonoVal)
            ->where('pay_date', $payDate)
            ->where('type', 1)
            ->exists();

        if ($exactDuplicate) {
            $this->stats['pays_duplicated']++;

            return;
        }

        $pay = Pay::create([
            'user_id' => $userId,
            'quota_id' => $quota->id,
            'type' => 1,
            'amount' => $abonoVal,
            'status' => 2,
            'pay_date' => $payDate,
            'reference' => $reference,
            'vaucher' => null,
            'pay_method' => 1,
            'pay_id' => '',
        ]);

        $pay->quotas()->attach($quota->id);

        $quota->update(['status' => 3]);

        $this->stats['pays_created']++;
    }

    private function resolveDepartaments(mixed $code): ?array
    {
        if ($code === null || $code === '') {
            return null;
        }

        // Numerico (103, 202, etc.)
        if (is_int($code) || (is_float($code) && floor($code) === $code) || (is_string($code) && ctype_digit(trim($code)))) {
            $number = 'DPT-'.(int) $code;

            return [['number' => $number, 'type' => Departament::TYPE_DEPARTAMENTO, 'description' => 'Departamento '.$number]];
        }

        $codeStr = (string) $code;

        // Saltar prefijo PAC del codigo cliente (PAC103 -> 103, PACESTA-131 -> ESTA-131)
        // El campo Predio ya viene limpio sin prefijo PAC

        // Multi-linea (ESTA-046\nESTA-062\nESTA-100)
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

        // Una sola linea
        $parsed = $this->parseSinglePredio($codeStr);

        return $parsed === null ? null : [$parsed];
    }

    private function parseSinglePredio(string $predio): ?array
    {
        $predio = strtoupper(trim($predio));

        if ($predio === '' || $predio === 'S/ID' || $predio === 'ESTA-S/ID') {
            return null;
        }

        // DEPA-103 -> DPT-103
        if (str_starts_with($predio, 'DEPA-')) {
            $suffix = substr($predio, 5);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'DPT-'.$suffix, 'type' => Departament::TYPE_DEPARTAMENTO, 'description' => 'Departamento DPT-'.$suffix];
        }

        // ESTA-131 -> EST-131
        if (str_starts_with($predio, 'ESTA-')) {
            $suffix = substr($predio, 5);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'EST-'.$suffix, 'type' => Departament::TYPE_ESTACIONAMIENTO, 'description' => 'Estacionamiento EST-'.$suffix];
        }

        // DEPO-184 -> DPO-184
        if (str_starts_with($predio, 'DEPO-')) {
            $suffix = substr($predio, 5);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'DPO-'.$suffix, 'type' => Departament::TYPE_DEPOSITO, 'description' => 'Deposito DPO-'.$suffix];
        }

        // LAV-001 -> LAV-001 (sin cambio de prefijo)
        if (str_starts_with($predio, 'LAV-')) {
            $suffix = substr($predio, 4);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'LAV-'.$suffix, 'type' => Departament::TYPE_LAV, 'description' => 'Lavanderia LAV-'.$suffix];
        }

        return null;
    }

    private function parsePeriodo(mixed $periodo): ?array
    {
        if ($periodo === null || $periodo === '') {
            return null;
        }

        // "Periodo 2025"
        if (is_string($periodo) && str_contains($periodo, 'Periodo')) {
            preg_match('/(\d{4})/', $periodo, $m);
            if (! $m) {
                return null;
            }
            $year = (int) $m[1];

            return ['month' => 0, 'year' => $year, 'label' => 'Periodo '.$year];
        }

        // DateTime de Excel (2026-08-01 00:00:00)
        if ($periodo instanceof Carbon) {
            $month = (int) $periodo->format('m');
            $year = (int) $periodo->format('Y');

            return ['month' => $month, 'year' => $year, 'label' => $this->monthName($month).' - '.$year];
        }

        // Numero serial de Excel (46023 = fecha serial Excel)
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

        // String fecha "2026-08-01"
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

    private function skip(mixed $predio, string $motivo): void
    {
        $this->stats['departments_skipped']++;
        $this->skipped[] = [
            'predio' => (string) $predio,
            'motivo' => $motivo,
        ];
    }

    private function printSummary(): void
    {
        $this->table(
            ['Metrica', 'Valor'],
            [
                ['Filas procesadas', $this->stats['rows']],
                ['--- CUOTAS ---', ''],
                ['Cuotas creadas', $this->stats['quotas_created']],
                ['Cuotas duplicadas (ya existian)', $this->stats['quotas_duplicated']],
                ['Lecturas de agua vinculadas', $this->stats['water_readings_linked']],
                ['--- PAGOS ---', ''],
                ['Filas con abono > 0', $this->stats['rows_with_abono']],
                ['Pagos creados (status 2 - Exitoso)', $this->stats['pays_created']],
                ['Pagos duplicados (skipped)', $this->stats['pays_duplicated']],
                ['Cuota no encontrada para pago', $this->stats['quotas_not_found_for_pay']],
                ['--- GENERALES ---', ''],
                ['Departamentos no encontrados en BD', $this->stats['departments_not_found']],
                ['Departamentos sin user_id', $this->stats['user_not_found']],
                ['Total registros saltados', $this->stats['departments_skipped']],
            ]
        );
    }

    private function generateSkippedReport(): void
    {
        if (empty($this->skipped)) {
            $this->info('No hubo registros omitidos.');

            return;
        }

        $reportPath = storage_path('app/import-cuotas-pagos-agosto.md');
        $lines = [];
        $lines[] = '# Reporte de omitidos - Importacion Cuotas y Pagos Agosto 2026';
        $lines[] = '';
        $lines[] = 'Generado: '.now()->format('d/m/Y H:i:s');
        $lines[] = '';
        $lines[] = 'Total omitidos: '.count($this->skipped);
        $lines[] = '';
        $lines[] = '| Predio | Motivo |';
        $lines[] = '|--------|--------|';

        foreach ($this->skipped as $s) {
            $escaped = str_replace('|', '\\|', $s['predio'] ?? '');
            $motivoEscaped = str_replace('|', '\\|', $s['motivo'] ?? '');
            $lines[] = "| {$escaped} | {$motivoEscaped} |";
        }

        file_put_contents($reportPath, implode("\n", $lines));
        $this->warn("\n".count($this->skipped)." registros omitidos. Reporte: $reportPath");
    }
}
