<?php

namespace App\Console\Commands;

use App\Models\CreditBalance;
use App\Models\CreditTransaction;
use App\Models\Departament;
use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportCreditBalances extends Command
{
    protected $signature = 'import:credit-balances
                            {file? : Ruta al archivo .xlsm (por defecto referencias/pagos_agosto.xlsm)}';

    protected $description = 'Importa saldos a favor desde la hoja DEVOL PARA SEPT. 26 de pagos_agosto.xlsm';

    private const SHEET_NAME = 'DEVOL PARA SEPT. 26';

    private const FIRST_DATA_ROW = 5;

    private const LAST_DATA_ROW = 224;

    private array $stats = [
        'rows' => 0,
        'credits_created' => 0,
        'credits_updated' => 0,
        'departments_not_found' => 0,
        'departments_skipped' => 0,
        'zero_amount' => 0,
    ];

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

        $this->info('Importando saldos a favor desde: '.self::SHEET_NAME);
        $this->newLine();

        // Agrupar montos por departamento (puede haber filas duplicadas)
        $amountsByDept = [];
        $totalRows = self::LAST_DATA_ROW - self::FIRST_DATA_ROW + 1;
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();

        for ($row = self::FIRST_DATA_ROW; $row <= self::LAST_DATA_ROW; $row++) {
            $this->processRow($sheet, $row, $amountsByDept);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Crear/actualizar credit_balances
        $this->info('Guardando saldos en credit_balances...');
        $this->saveBalances($amountsByDept);

        $this->printSummary();

        return 0;
    }

    private function processRow($sheet, int $row, array &$amountsByDept): void
    {
        $this->stats['rows']++;

        $predio = $this->cellValue($sheet, $row, 3); // Columna C
        $junio = $this->cellNumericValue($sheet, $row, 4); // Columna D
        $julio = $this->cellNumericValue($sheet, $row, 5); // Columna E
        $agosto = $this->cellNumericValue($sheet, $row, 6); // Columna F

        // Saltar filas vacias
        if ($predio === null || $predio === '') {
            $this->stats['departments_skipped']++;

            return;
        }

        // Resolver departamentos desde el codigo Predio
        $departamentsData = $this->resolveDepartaments($predio);
        if ($departamentsData === null) {
            $this->stats['departments_skipped']++;

            return;
        }

        // Calcular total (D + E + F)
        $total = $junio + $julio + $agosto;

        if ($total <= 0) {
            $this->stats['zero_amount']++;

            return;
        }

        // Si hay varios departamentos en una fila, dividir el monto
        $totalPerDept = $total / count($departamentsData);

        foreach ($departamentsData as $deptData) {
            $departament = Departament::where('number', $deptData['number'])->first();
            if (! $departament) {
                $this->stats['departments_not_found']++;
                $this->warn("  Depto no encontrado: {$deptData['number']} (fila $row)");

                return;
            }

            $key = $departament->id;
            $amountsByDept[$key] = ($amountsByDept[$key] ?? 0) + $totalPerDept;
        }
    }

    private function saveBalances(array $amountsByDept): void
    {
        $bar = $this->output->createProgressBar(count($amountsByDept));
        $bar->start();

        foreach ($amountsByDept as $departamentId => $amount) {
            $amount = round($amount, 2);

            // Buscar saldo existente
            $balance = CreditBalance::where('departament_id', $departamentId)->first();

            if ($balance) {
                $oldBalance = $balance->balance;
                $balance->balance = $amount;
                $balance->save();

                // Registrar transaccion si hay cambio
                if (abs($oldBalance - $amount) > 0.01) {
                    CreditTransaction::create([
                        'departament_id' => $departamentId,
                        'type' => CreditTransaction::TYPE_CREATED,
                        'amount' => $amount - $oldBalance,
                        'balance_after' => $amount,
                        'description' => 'Importacion desde Excel - DEVOL PARA SEPT. 26',
                        'created_at' => Carbon::now(),
                    ]);
                    $this->stats['credits_updated']++;
                }
            } else {
                CreditBalance::create([
                    'departament_id' => $departamentId,
                    'balance' => $amount,
                ]);

                CreditTransaction::create([
                    'departament_id' => $departamentId,
                    'type' => CreditTransaction::TYPE_CREATED,
                    'amount' => $amount,
                    'balance_after' => $amount,
                    'description' => 'Importacion desde Excel - DEVOL PARA SEPT. 26',
                    'created_at' => Carbon::now(),
                ]);
                $this->stats['credits_created']++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function cellValue($sheet, int $row, int $col)
    {
        return $sheet->getCell([$col, $row])->getValue();
    }

    private function cellNumericValue($sheet, int $row, int $col): float
    {
        $val = $sheet->getCell([$col, $row])->getValue();

        if (is_numeric($val)) {
            return (float) $val;
        }

        return 0.0;
    }

    private function resolveDepartaments(mixed $code): ?array
    {
        if ($code === null || $code === '') {
            return null;
        }

        // Numerico (103, 202, etc.)
        if (is_int($code) || (is_float($code) && floor($code) === $code) || (is_string($code) && ctype_digit(trim($code)))) {
            $number = 'DPT-'.(int) $code;

            return [['number' => $number, 'type' => Departament::TYPE_DEPARTAMENTO]];
        }

        $codeStr = (string) $code;

        // Multi-linea
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

        // DEPA-103 -> DPT-103
        if (str_starts_with($predio, 'DEPA-')) {
            $suffix = substr($predio, 5);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'DPT-'.$suffix, 'type' => Departament::TYPE_DEPARTAMENTO];
        }

        // ESTA-131 -> EST-131
        if (str_starts_with($predio, 'ESTA-')) {
            $suffix = substr($predio, 5);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'EST-'.$suffix, 'type' => Departament::TYPE_ESTACIONAMIENTO];
        }

        // DEPO-184 -> DPO-184
        if (str_starts_with($predio, 'DEPO-')) {
            $suffix = substr($predio, 5);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'DPO-'.$suffix, 'type' => Departament::TYPE_DEPOSITO];
        }

        // LAV-001
        if (str_starts_with($predio, 'LAV-')) {
            $suffix = substr($predio, 4);
            if (! ctype_digit($suffix)) {
                return null;
            }

            return ['number' => 'LAV-'.$suffix, 'type' => Departament::TYPE_LAV];
        }

        return null;
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->info('=== RESUMEN ===');
        $this->info("Filas procesadas: {$this->stats['rows']}");
        $this->info("Saldos creados: {$this->stats['credits_created']}");
        $this->info("Saldos actualizados: {$this->stats['credits_updated']}");
        $this->info("Deptos no encontrados: {$this->stats['departments_not_found']}");
        $this->info("Filas omitidas (vacias): {$this->stats['departments_skipped']}");
        $this->info("Filas con monto cero: {$this->stats['zero_amount']}");
    }
}
