<?php

namespace App\Console\Commands;

use App\Models\Departament;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportMissingUnitsFromExcel extends Command
{
    protected $signature = 'import:missing-units
                            {file? : Ruta al archivo .xlsx}';

    protected $description = 'Crea estacionamientos y depositos faltantes desde la hoja DPTOS. del Excel';

    private const FILE_DEFAULT = 'referencias/LISTA DE PROPIETARIOS completo.xlsx';

    private const SHEET_NAME = 'DPTOS.';

    private const FIRST_DATA_ROW = 3;

    private array $stats = [
        'rows_processed' => 0,
        'dept_not_found' => 0,
        'estac_created' => 0,
        'estac_skipped' => 0,
        'dep_created' => 0,
        'dep_skipped' => 0,
    ];

    public function handle(): int
    {
        $file = $this->argument('file') ?: base_path(self::FILE_DEFAULT);

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

        $highestRow = $sheet->getHighestRow();

        $this->newLine();
        $this->info('=== CREANDO UNIDADES FALTANTES (ESTACIONAMIENTOS Y DEPOSITOS) ===');
        $this->newLine();

        $bar = $this->output->createProgressBar($highestRow - self::FIRST_DATA_ROW + 1);
        $bar->start();

        for ($row = self::FIRST_DATA_ROW; $row <= $highestRow; $row++) {
            $this->processRow($sheet, $row);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->printStats();

        return 0;
    }

    private function processRow($sheet, int $row): void
    {
        $dpto = $sheet->getCell([7, $row])->getValue();

        if ($dpto === null || $dpto === '' || ! is_numeric($dpto)) {
            return;
        }

        $this->stats['rows_processed']++;

        $deptNumber = 'DPT-'.(int) $dpto;
        $department = Departament::where('type', Departament::TYPE_DEPARTAMENTO)
            ->where('number', $deptNumber)
            ->first();

        if (! $department) {
            $this->warn("  Fila $row: Departamento $deptNumber no encontrado en BD");
            $this->stats['dept_not_found']++;

            return;
        }

        $userId = $department->user_id;

        // Procesar estacionamientos (col 12)
        $estac = $sheet->getCell([12, $row])->getValue();
        if ($estac !== null && $estac !== '') {
            $codes = $this->parseCodes((string) $estac, 'ESTA-', 'EST-', Departament::TYPE_ESTACIONAMIENTO);
            foreach ($codes as $code) {
                $exists = Departament::where('number', $code)->exists();
                if ($exists) {
                    $this->stats['estac_skipped']++;

                    continue;
                }
                Departament::create([
                    'number' => $code,
                    'type' => Departament::TYPE_ESTACIONAMIENTO,
                    'user_id' => $userId,
                    'participation_percentage' => 0.001269,
                    'area' => 0,
                    'description' => 'Estacionamiento '.$code,
                ]);
                $this->stats['estac_created']++;
            }
        }

        // Procesar depositos (col 16)
        $dep = $sheet->getCell([16, $row])->getValue();
        if ($dep !== null && $dep !== '') {
            $codes = $this->parseCodes((string) $dep, 'DEPO-', 'DPO-', Departament::TYPE_DEPOSITO);
            foreach ($codes as $code) {
                $exists = Departament::where('number', $code)->exists();
                if ($exists) {
                    $this->stats['dep_skipped']++;

                    continue;
                }
                Departament::create([
                    'number' => $code,
                    'type' => Departament::TYPE_DEPOSITO,
                    'user_id' => $userId,
                    'participation_percentage' => 0.0003675,
                    'area' => 0,
                    'description' => 'Deposito '.$code,
                ]);
                $this->stats['dep_created']++;
            }
        }
    }

    private function parseCodes(string $value, string $excelPrefix, string $bdPrefix, int $type): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $value);
        $result = [];

        foreach ($lines as $line) {
            $line = strtoupper(trim($line));
            if ($line === '' || ! str_starts_with($line, $excelPrefix)) {
                continue;
            }
            $suffix = substr($line, strlen($excelPrefix));
            if (ctype_digit($suffix)) {
                $result[] = $bdPrefix.$suffix;
            }
        }

        return $result;
    }

    private function printStats(): void
    {
        $this->line('========================================');
        $this->info('RESUMEN:');
        $this->line("  Filas procesadas:      {$this->stats['rows_processed']}");
        $this->line("  Depto no encontrado:   {$this->stats['dept_not_found']}");
        $this->line('  Estacionamientos:');
        $this->line("    Creados:             {$this->stats['estac_created']}");
        $this->line("    Ya existian:         {$this->stats['estac_skipped']}");
        $this->line('  Depositos:');
        $this->line("    Creados:             {$this->stats['dep_created']}");
        $this->line("    Ya existian:         {$this->stats['dep_skipped']}");
        $this->line('========================================');
    }
}
