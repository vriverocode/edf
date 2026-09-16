<?php

namespace App\Console\Commands;

use App\Models\Departament;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ValidatePropietariosFromExcel extends Command
{
    protected $signature = 'import:validate-propietarios
                            {file? : Ruta al archivo .xlsx}';

    protected $description = 'Valida departamentos, estacionamientos y depositos del Excel contra la BD';

    private const FILE_DEFAULT = 'referencias/LISTA DE PROPIETARIOS completo.xlsx';

    private array $sheets = [
        'DPTO' => ['type' => Departament::TYPE_DEPARTAMENTO, 'prefix' => 'DPT-', 'label' => 'Departamentos'],
        'ESTAC' => ['type' => Departament::TYPE_ESTACIONAMIENTO, 'prefix' => 'EST-', 'label' => 'Estacionamientos'],
        'DEP' => ['type' => Departament::TYPE_DEPOSITO, 'prefix' => 'DPO-', 'label' => 'Depositos'],
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

        $this->newLine();
        $this->info('=== VALIDACION PROPIETARIOS vs BD ===');
        $this->newLine();

        $totalExcel = 0;
        $totalExist = 0;
        $totalFaltan = 0;
        $allMissing = [];

        foreach ($this->sheets as $sheetName => $config) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (! $sheet) {
                $this->warn("Hoja '$sheetName' no encontrada, saltando...");

                continue;
            }

            $highestRow = $sheet->getHighestRow();
            $predios = [];

            for ($row = 2; $row <= $highestRow; $row++) {
                $predio = $sheet->getCell([3, $row])->getValue();
                if ($predio === null || $predio === '' || $predio === 'PREDIO') {
                    continue;
                }

                $parsed = $this->parsePredio((string) $predio, $config['prefix']);
                if ($parsed !== null) {
                    $predios[$row] = $parsed;
                }
            }

            $numbers = array_values($predios);
            $existing = Departament::whereIn('number', $numbers)->pluck('number')->toArray();
            $missing = array_diff($numbers, $existing);

            $totalExcel += count($numbers);
            $totalExist += count($existing);
            $totalFaltan += count($missing);

            $this->info("Hoja: {$config['label']} ({$config['prefix']}*)");
            $this->line('  Total en Excel: '.count($numbers));
            $this->line('  Existen en BD:  '.count($existing));

            if (empty($missing)) {
                $this->line('  <info>OK - Todos existen en BD</info>');
            } else {
                $this->line('  <error>FALTAN POR CREAR: '.count($missing).'</error>');
                foreach ($missing as $num) {
                    $this->line("    - $num");
                    $allMissing[] = ['sheet' => $sheetName, 'number' => $num, 'prefix' => $config['prefix']];
                }
            }

            $this->newLine();
        }

        $this->line('========================================');
        $this->info('RESUMEN:');
        $this->line("  Total en Excel:   $totalExcel");
        $this->line("  Existen en BD:    $totalExist");
        $this->line("  Faltan por crear: $totalFaltan");
        $this->line('========================================');

        if (! empty($allMissing)) {
            $csvFile = base_path('referencias/faltantes_'.date('Ymd_His').'.csv');
            $fp = fopen($csvFile, 'w');
            fputcsv($fp, ['HOJA', 'NUMERO_BD', 'PREDIO_EXCEL']);
            foreach ($allMissing as $m) {
                fputcsv($fp, [$m['sheet'], $m['number'], $m['prefix'] === 'DPT-' ? 'DEPA-'.substr($m['number'], 4) : ($m['prefix'] === 'EST-' ? 'ESTA-'.substr($m['number'], 4) : 'DEPO-'.substr($m['number'], 4))]);
            }
            fclose($fp);
            $this->newLine();
            $this->info("CSV guardado en: $csvFile");
        }

        return 0;
    }

    private function parsePredio(string $predio, string $targetPrefix): ?string
    {
        $predio = strtoupper(trim($predio));

        if ($predio === '' || $predio === 'S/ID' || $predio === 'ESTA-S/ID') {
            return null;
        }

        // DEPA-103 -> DPT-103
        if (str_starts_with($predio, 'DEPA-')) {
            $suffix = substr($predio, 5);

            return ctype_digit($suffix) ? 'DPT-'.$suffix : null;
        }

        // ESTA-131 -> EST-131
        if (str_starts_with($predio, 'ESTA-')) {
            $suffix = substr($predio, 5);

            return ctype_digit($suffix) ? 'EST-'.$suffix : null;
        }

        // DEPO-184 -> DPO-184
        if (str_starts_with($predio, 'DEPO-')) {
            $suffix = substr($predio, 5);

            return ctype_digit($suffix) ? 'DPO-'.$suffix : null;
        }

        // Numerico puro (101, 102) -> DPT-101
        if (ctype_digit($predio)) {
            return $targetPrefix.$predio;
        }

        return null;
    }
}
