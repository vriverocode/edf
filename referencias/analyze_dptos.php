<?php

require __DIR__.'/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$file = __DIR__.'/LISTA DE PROPIETARIOS completo.xlsx';
$reader = IOFactory::createReaderForFile($file);
$reader->setReadDataOnly(true);
$spreadsheet = $reader->load($file);
$sheet = $spreadsheet->getSheetByName('DPTOS.');
$highestRow = $sheet->getHighestRow();

echo "=== ANALISIS HOJA DPTOS. ===\n";
echo "Total filas: $highestRow\n\n";

// Check col 7 (DEPARTAMENTOS) and col 12 (ESTAC) and col 16 (DEP)
$deptCount = 0;
$estacCount = 0;
$depCount = 0;
$usersFound = 0;
$usersNotFound = 0;
$emptyDept = 0;

for ($row = 3; $row <= $highestRow; $row++) {
    $dpto = $sheet->getCell([7, $row])->getValue();
    $estac = $sheet->getCell([12, $row])->getValue();
    $dep = $sheet->getCell([16, $row])->getValue();
    $propietario = $sheet->getCell([2, $row])->getValue();

    if ($dpto === null || $dpto === '') {
        $emptyDept++;

        continue;
    }

    $deptCount++;

    if ($estac !== null && $estac !== '') {
        $lines = preg_split('/\r\n|\n|\r/', (string) $estac);
        $estacCount += count($lines);
        echo "Row $row: DPTO=$dpto, PROPIETARIO=$propietario, ESTAC=".implode('|', $lines)."\n";
    }

    if ($dep !== null && $dep !== '') {
        $lines = preg_split('/\r\n|\n|\r/', (string) $dep);
        $depCount += count($lines);
        echo "Row $row: DPTO=$dpto, PROPIETARIO=$propietario, DEP=".implode('|', $lines)."\n";
    }
}

echo "\n=== RESUMEN ===\n";
echo "Filas con DPTO: $deptCount\n";
echo "Filas vacias DPTO: $emptyDept\n";
echo "Total ESTAC: $estacCount\n";
echo "Total DEP: $depCount\n";

// Now check which departments exist in BD
echo "\n=== VERIFICACION BD ===\n";
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Departament;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$allDepts = [];
for ($row = 3; $row <= $highestRow; $row++) {
    $dpto = $sheet->getCell([7, $row])->getValue();
    if ($dpto !== null && $dpto !== '' && is_numeric($dpto)) {
        $allDepts[] = (int) $dpto;
    }
}
$allDepts = array_unique($allDepts);
sort($allDepts);

$existing = Departament::where('type', 1)
    ->whereIn(DB::raw('CAST(SUBSTRING(number, 5) AS UNSIGNED)'), $allDepts)
    ->pluck('number', DB::raw('CAST(SUBSTRING(number, 5) AS UNSIGNED)'))
    ->toArray();

$missing = array_diff($allDepts, array_keys($existing));
echo 'Total deptos unicos en Excel: '.count($allDepts)."\n";
echo 'Deptos existentes en BD: '.count($existing)."\n";
echo 'Deptos faltantes en BD: '.count($missing)."\n";
if (! empty($missing)) {
    echo 'Faltantes: '.implode(', ', $missing)."\n";
}

// Check participation percentage for parking
echo "\n=== PORCENTAJE PARTICIPACION ESTAC ===\n";
$sheetEstac = $spreadsheet->getSheetByName('ESTAC');
for ($row = 2; $row <= min(10, $sheetEstac->getHighestRow()); $row++) {
    $predio = $sheetEstac->getCell([3, $row])->getValue();
    $part = $sheetEstac->getCell([6, $row])->getValue();
    echo "Row $row: PREDIO=$predio, %PARTICIPACION=$part\n";
}
