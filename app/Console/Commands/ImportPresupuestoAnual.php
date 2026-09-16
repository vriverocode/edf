<?php

namespace App\Console\Commands;

use App\Models\AnnualBudget;
use App\Models\Expense;
use App\Models\ServiceCategory;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPresupuestoAnual extends Command
{
    protected $signature = 'import:presupuesto-anual
                            {file? : Ruta al archivo .xlsx}
                            {year? : Año del presupuesto (default: 2026)}';

    protected $description = 'Importa el presupuesto anual desde Excel y crea templates de gastos';

    private const FILE_DEFAULT = 'referencias/Presupuesto_2026.xlsx';

    private array $categories = [
        'SERVICIOS BASICOS' => ['start' => 7, 'end' => 10],
        'ADMIN' => ['start' => 12, 'end' => 12],
        'MANTENIMIENTOS PREVENTIVOS' => ['start' => 15, 'end' => 20],
        'GASTOS OPERATIVOS' => ['start' => 22, 'end' => 22],
        'MATERIALES CONSUMIBLES' => ['start' => 25, 'end' => 27],
        'OTROS' => ['start' => 30, 'end' => 32],
    ];

    public function handle(): int
    {
        $file = $this->argument('file') ?: base_path(self::FILE_DEFAULT);
        $year = (int) ($this->argument('year') ?: 2026);

        if (! file_exists($file)) {
            $this->error("Archivo no encontrado: $file");

            return 1;
        }

        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);

        $sheetName = "año_{$year}";
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (! $sheet) {
            $this->error("Hoja '$sheetName' no encontrada. Hojas disponibles: ".implode(', ', $spreadsheet->getSheetNames()));

            return 1;
        }

        $this->newLine();
        $this->info("=== IMPORTANDO PRESUPUESTO ANUAL $year ===");
        $this->newLine();

        // Calcular total mensual desde el Excel (fila 34 = TOTAL GASTOS)
        $totalMonthly = 0;
        $bCell = $sheet->getCell('B34')->getValue();
        if (is_string($bCell) && str_starts_with($bCell, '=')) {
            // Es fórmula, calcular manualmente
            $totalMonthly = $this->calculateTotalMonthly($sheet);
        } else {
            $totalMonthly = (float) $bCell;
        }

        $budget = AnnualBudget::updateOrCreate(
            ['year' => $year],
            [
                'name' => "Presupuesto $year",
                'total_monthly_budget' => $totalMonthly,
                'status' => AnnualBudget::STATUS_ACTIVO,
            ]
        );

        $this->info("Presupuesto creado/actualizado: {$budget->name} (ID: {$budget->id})");
        $this->line('  Total mensual: S/ '.number_format($totalMonthly, 2));
        $this->newLine();

        // Eliminar templates anteriores de este presupuesto
        Expense::where('annual_budget_id', $budget->id)
            ->where('is_template', true)
            ->delete();

        $created = 0;
        $sortOrder = 0;

        // Recorrer el Excel y crear templates
        $highestRow = $sheet->getHighestRow();
        for ($row = 6; $row <= min(35, $highestRow); $row++) {
            $concepto = $sheet->getCell([1, $row])->getValue();
            $monto = $sheet->getCell([2, $row])->getValue();

            // Saltar filas vacías, headers de categoría y filas de fórmula
            if ($concepto === null || $concepto === '') {
                continue;
            }

            $concepto = trim((string) $concepto);

            // Saltar headers de categoría (en mayúsculas y sin monto)
            if ($this->isCategoryHeader($concepto)) {
                continue;
            }

            // Saltar filas de totales y fórmulas
            if (str_starts_with($concepto, 'TOTAL') || str_starts_with($concepto, '(')) {
                continue;
            }

            // Saltar si el monto es 0 o es una fórmula
            if ($monto === null || $monto === '' || (is_string($monto) && str_starts_with($monto, '='))) {
                // Para ADMIN que es =20700*1.18, calcular
                if (str_contains($concepto, 'ADMIN') && is_string($monto) && str_starts_with($monto, '=')) {
                    $monto = 20700 * 1.18;
                } else {
                    continue;
                }
            }

            $amount = (float) $monto;
            if ($amount <= 0) {
                continue;
            }

            $categoryId = $this->resolveCategoryId($concepto);

            Expense::create([
                'annual_budget_id' => $budget->id,
                'service_category_id' => $categoryId,
                'amount' => $amount,
                'expense_type' => 1, // Ordinario/Recurrente
                'description' => $concepto,
                'status' => 1,
                'is_template' => true,
                'sort_order' => $sortOrder++,
            ]);

            $this->line("  + $concepto: S/ ".number_format($amount, 2));
            $created++;
        }

        $this->newLine();
        $this->line('========================================');
        $this->info('RESUMEN:');
        $this->line("  Presupuesto: {$budget->name}");
        $this->line('  Total mensual: S/ '.number_format($totalMonthly, 2));
        $this->line("  Templates creados: $created");
        $this->line('========================================');

        return 0;
    }

    private function isCategoryHeader(string $concepto): bool
    {
        $headers = [
            'SERVICIOS BASICOS',
            'ADMIN',
            'MANTENIMIENTOS PREVENTIVOS',
            'GASTOS OPERATIVOS / CONTINGENCIAS',
            'GASTOS OPERATIVOS',
            'MATERIALES CONSUMIBLES',
            'EGRESOS',
            'PP-año',
        ];

        foreach ($headers as $header) {
            if (strtoupper($concepto) === $header || str_starts_with(strtoupper($concepto), 'PP-')) {
                return true;
            }
        }

        return false;
    }

    private function resolveCategoryId(string $description): ?int
    {
        $mapping = [
            'Agua' => 'Servicios Básicos',
            'Energia' => 'Servicios Básicos',
            'Internet' => 'Servicios Básicos',
            'Telefonia' => 'Servicios Básicos',
            'ADMIN' => 'Administración',
            'Ascensores' => 'Mantenimiento',
            'Mantenimientos' => 'Mantenimiento',
            'GASTOS OPERATIVOS' => 'Gastos Operativos',
            'Utiles' => 'Materiales',
            'Materiales' => 'Materiales',
            'Gastos legales' => 'Otros',
            'Servicio app' => 'Otros',
            'Gastos bancarios' => 'Otros',
        ];

        foreach ($mapping as $keyword => $categoryName) {
            if (str_contains(strtoupper($description), strtoupper($keyword))) {
                $category = ServiceCategory::firstOrCreate(
                    ['name' => $categoryName],
                    ['status' => 1]
                );

                return $category->id;
            }
        }

        $category = ServiceCategory::firstOrCreate(
            ['name' => 'Sin categoría'],
            ['status' => 1]
        );

        return $category->id;
    }

    private function calculateTotalMonthly($sheet): float
    {
        // Sumar montos de filas individuales (no headers ni totales)
        $total = 0;
        for ($row = 6; $row <= 35; $row++) {
            $concepto = $sheet->getCell([1, $row])->getValue();
            $monto = $sheet->getCell([2, $row])->getValue();

            if ($concepto === null || $concepto === '') {
                continue;
            }

            $concepto = trim((string) $concepto);

            if ($this->isCategoryHeader($concepto)) {
                continue;
            }

            if (str_starts_with($concepto, 'TOTAL') || str_starts_with($concepto, '(')) {
                continue;
            }

            if ($monto === null || $monto === '' || (is_string($monto) && str_starts_with($monto, '='))) {
                if (str_contains($concepto, 'ADMIN') && is_string($monto) && str_starts_with($monto, '=')) {
                    $total += 20700 * 1.18;
                }

                continue;
            }

            $amount = (float) $monto;
            if ($amount > 0) {
                $total += $amount;
            }
        }

        return $total;
    }
}
