<?php

namespace App\Console\Commands;

use App\Models\Departament;
use App\Models\Quota;
use App\Models\WaterReading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportWaterReadingsJuly extends Command
{
    protected $signature = 'import:water-readings-july';

    protected $description = 'Importa lecturas de agua julio desde agua_julio.xlsx y corrige las de agosto (÷10)';

    private const EXCEL_PATH = 'referencias/agua_julio.xlsx';

    private const FIRST_DATA_ROW = 6;

    private const LAST_DATA_ROW = 225;

    private const YEAR = 2026;

    private const JULY_MONTH = 7;

    private const AUGUST_MONTH = 8;

    private const AUGUST_PRICE = 4.34;

    private array $stats = [
        'july_created' => 0,
        'july_skipped_duplicates' => 0,
        'july_skipped_no_depto' => 0,
        'july_skipped_zero' => 0,
        'july_quotas_linked' => 0,
        'august_corrected' => 0,
        'august_skipped_no_excel' => 0,
        'august_skipped_p11' => 0,
    ];

    private array $skipped = [];

    public function handle(): int
    {
        $file = base_path(self::EXCEL_PATH);

        if (! file_exists($file)) {
            $this->error("Archivo no encontrado: $file");

            return 1;
        }

        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($file);
        $sheet = $spreadsheet->getActiveSheet();

        $this->info("Procesando hoja '".self::EXCEL_PATH."'...\n");

        $bar = $this->output->createProgressBar(self::LAST_DATA_ROW - self::FIRST_DATA_ROW + 1);
        $bar->start();

        DB::transaction(function () use ($sheet) {
            $excelData = $this->readExcelData($sheet);

            $this->createJulyReadings($excelData);
            $this->fixAugustReadings($excelData);
            $this->linkJulyQuotas();
        });

        $bar->finish();
        $this->newLine(2);

        $this->printSummary();
        $this->printSkipped();

        return 0;
    }

    private function readExcelData(Worksheet $sheet): array
    {
        $data = [];

        for ($row = self::FIRST_DATA_ROW; $row <= self::LAST_DATA_ROW; $row++) {
            $predio = $sheet->getCell([1, $row])->getValue();

            if ($predio === null || $predio === '' || ! is_numeric($predio)) {
                continue;
            }

            $deptNum = (int) $predio;
            $junio = $sheet->getCell([3, $row])->getValue();
            $julio = $sheet->getCell([4, $row])->getValue();

            $junioVal = is_numeric($junio) ? (float) $junio : null;
            $julioVal = is_numeric($julio) ? (float) $julio : null;

            if ($julioVal !== null && $julioVal > 0 && ! isset($data[$deptNum])) {
                $data[$deptNum] = ['junio' => $junioVal, 'julio' => $julioVal];
            }
        }

        return $data;
    }

    private function createJulyReadings(array $excelData): void
    {
        foreach ($excelData as $deptNum => $readings) {
            $departament = Departament::where('number', 'dpt-'.$deptNum)->first();

            if (! $departament) {
                $this->stats['july_skipped_no_depto']++;
                $this->skipped[] = ['dept' => 'dpt-'.$deptNum, 'reason' => 'Departamento no encontrado en DB'];

                continue;
            }

            $exists = WaterReading::where('departament_id', $departament->id)
                ->where('month', self::JULY_MONTH)
                ->where('year', self::YEAR)
                ->exists();

            if ($exists) {
                $this->stats['july_skipped_duplicates']++;

                continue;
            }

            WaterReading::create([
                'departament_id' => $departament->id,
                'month' => self::JULY_MONTH,
                'year' => self::YEAR,
                'previous_reading' => $readings['junio'],
                'current_reading' => $readings['julio'],
                'm3_price' => 0,
                'amount' => 0,
                'is_initial' => 0,
            ]);

            $this->stats['july_created']++;
        }
    }

    private function fixAugustReadings(array $excelData): void
    {
        $augustReadings = WaterReading::where('month', self::AUGUST_MONTH)
            ->where('year', self::YEAR)
            ->with('departament')
            ->get();

        foreach ($augustReadings as $reading) {
            $deptNumber = $reading->departament->number;

            if (str_starts_with($deptNumber, 'P11')) {
                $this->stats['august_skipped_p11']++;

                continue;
            }

            $n = (int) str_replace('dpt-', '', $deptNumber);

            if (! isset($excelData[$n])) {
                $this->stats['august_skipped_no_excel']++;

                continue;
            }

            $julioValue = $excelData[$n]['julio'];
            $newCurrent = $reading->current_reading / 10;
            $consumption = max(0, $newCurrent - $julioValue);
            $newAmount = round($consumption * self::AUGUST_PRICE, 2);

            $reading->update([
                'previous_reading' => $julioValue,
                'current_reading' => $newCurrent,
                'amount' => $newAmount,
            ]);

            $this->stats['august_corrected']++;
        }
    }

    private function linkJulyQuotas(): void
    {
        $readings = WaterReading::where('month', self::JULY_MONTH)
            ->where('year', self::YEAR)
            ->get();

        foreach ($readings as $reading) {
            $linked = Quota::where('departament_id', $reading->departament_id)
                ->where('month', self::JULY_MONTH)
                ->whereYear('due_date', self::YEAR)
                ->whereNull('water_reading_id')
                ->update([
                    'water_reading_id' => $reading->id,
                    'water_amount' => 0,
                ]);

            $this->stats['july_quotas_linked'] += $linked;
        }
    }

    private function printSummary(): void
    {
        $this->table(
            ['Metrica', 'Valor'],
            [
                ['--- JULIO ---', ''],
                ['WaterReadings julio creados', $this->stats['july_created']],
                ['Saltados (ya existían)', $this->stats['july_skipped_duplicates']],
                ['Saltados (depto no encontrado)', $this->stats['july_skipped_no_depto']],
                ['Quotas month 7 vinculadas', $this->stats['july_quotas_linked']],
                ['', ''],
                ['--- AGOSTO ---', ''],
                ['WaterReadings agosto corregidos', $this->stats['august_corrected']],
                ['Saltados (sin dato en Excel)', $this->stats['august_skipped_no_excel']],
                ['Saltados (P11-020)', $this->stats['august_skipped_p11']],
            ]
        );
    }

    private function printSkipped(): void
    {
        if (! empty($this->skipped)) {
            $this->warn('Departamentos no encontrados:');

            foreach ($this->skipped as $s) {
                $this->line('  - '.$s['dept'].': '.$s['reason']);
            }
        }
    }
}
