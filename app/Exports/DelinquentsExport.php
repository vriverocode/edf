<?php

declare(strict_types=1);

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;

class DelinquentsExport implements FromCollection, ShouldAutoSize
{
    use RegistersEventListeners;

    private Collection $data;

    private array $allRows = [];

    private int $totalCols = 0;

    private int $dataStartRow = 5;

    private int $dataEndRow = 5;

    public function __construct(Collection $data)
    {
        $this->data = $data;
        $this->prepareData();
    }

    public function collection(): Collection
    {
        return collect($this->allRows);
    }

    public function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet;
        $lastCol = self::getColumnLetter($this->totalCols);

        // --- Title row (row 1) ---
        $sheet->mergeCells("A1:{$lastCol}1");
        $titleStyle = $sheet->getStyle('A1');
        $titleStyle->getFont()->setBold(true)->setSize(14);
        $titleStyle->getAlignment()->setHorizontal('center')->setVertical('center');
        $sheet->getRowDimension(1)->setRowHeight(30);

        // --- Subtitle row (row 2) ---
        $sheet->mergeCells("A2:{$lastCol}2");
        $subtitleStyle = $sheet->getStyle('A2');
        $subtitleStyle->getFont()->setBold(true)->setSize(11);
        $subtitleStyle->getAlignment()->setHorizontal('center')->setVertical('center');
        $sheet->getRowDimension(2)->setRowHeight(22);

        // --- Header row (row 4) ---
        $headerRange = "A4:{$lastCol}4";
        $headerStyle = $sheet->getStyle($headerRange);
        $headerStyle->getFont()->setBold(true)->setSize(10);
        $headerStyle->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
        $headerStyle->getBorders()->getBottom()->setBorderStyle(
            Border::BORDER_THIN
        );
        $sheet->getRowDimension(4)->setRowHeight(35);

        // --- Data rows: currency format for amount columns (C onwards) ---
        if ($this->totalCols > 2) {
            $firstAmountCol = 'C';
            $dataStart = $this->dataStartRow;
            $dataEnd = $this->dataEndRow;

            if ($dataEnd >= $dataStart) {
                $amountRange = "{$firstAmountCol}{$dataStart}:{$lastCol}{$dataEnd}";
                $sheet->getStyle($amountRange)->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        // --- Total row: bold + border top ---
        $totalRow = $this->dataEndRow + 1;
        $totalRange = "A{$totalRow}:{$lastCol}{$totalRow}";
        $totalStyle = $sheet->getStyle($totalRange);
        $totalStyle->getFont()->setBold(true)->setSize(10);
        $totalStyle->getBorders()->getTop()->setBorderStyle(
            Border::BORDER_THIN
        );
        $totalStyle->getAlignment()->setVertical('center');

        if ($this->totalCols > 2) {
            $sheet->getStyle("C{$totalRow}:{$lastCol}{$totalRow}")
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // --- Department column width ---
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
    }

    private function prepareData(): void
    {
        // 1. Flatten all quotas from all users
        $allQuotas = [];
        foreach ($this->data as $entry) {
            $quotas = $entry['quotas'] ?? [];
            foreach ($quotas as $quota) {
                $allQuotas[] = $quota;
            }
        }

        if (empty($allQuotas)) {
            $this->buildEmptyResult();

            return;
        }

        // 2. Group by department number
        $byDepartment = collect($allQuotas)->groupBy('department');

        // 3. Determine current year from data
        $currentYear = now()->year;
        $previousYear = $currentYear - 1;

        // 4. Process each department
        $deptRows = $byDepartment->map(function ($quotas, $deptNumber) use ($currentYear) {
            $responsible = $quotas->first()['payment_responsible_name'] ?? '—';

            // Group amounts by (year, month)
            $amountsByPeriod = [];
            foreach ($quotas as $q) {
                $year = isset($q['due_date']) && $q['due_date']
                    ? Carbon::parse($q['due_date'])->year
                    : $currentYear;
                $month = (int) ($q['month'] ?? 0);
                $key = $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
                $amountsByPeriod[$key] = ($amountsByPeriod[$key] ?? 0) + (float) ($q['amount'] ?? 0);
            }

            return [
                'department' => $deptNumber,
                'responsible' => $responsible,
                'amounts' => $amountsByPeriod,
            ];
        });

        // 5. Collect all (year, month) keys across all departments
        $allKeys = $deptRows->pluck('amounts')
            ->flatMap(fn ($a) => array_keys($a))
            ->unique()
            ->sort()
            ->values();

        // 6. Separate previous year keys and current year keys
        $prevYearKeys = $allKeys->filter(function ($key) use ($previousYear) {
            return (int) substr($key, 0, 4) === $previousYear;
        })->values();

        $currentYearKeys = $allKeys->filter(function ($key) use ($currentYear) {
            return (int) substr($key, 0, 4) === $currentYear;
        })->values();

        // 7. Build column structure
        $columns = []; // [{type, key, label}]

        $columns[] = ['type' => 'dept', 'key' => 'department', 'label' => 'DEPARTAMENTO'];
        $columns[] = ['type' => 'name', 'key' => 'responsible', 'label' => 'PROPIETARIO'];

        // Previous year aggregated (one column per year if multiple)
        $prevYears = $prevYearKeys->map(fn ($k) => (int) substr($k, 0, 4))
            ->unique()->sort()->values();
        foreach ($prevYears as $py) {
            $columns[] = ['type' => 'period', 'key' => "prev_{$py}", 'label' => "PERIODO {$py}"];
        }

        // Current year individual months
        $monthLabels = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];

        foreach ($currentYearKeys as $ck) {
            $monthNum = (int) substr($ck, 5, 2);
            $columns[] = [
                'type' => 'month',
                'key' => $ck,
                'label' => ($monthLabels[$monthNum] ?? 'MES').' '.$currentYear,
            ];
        }

        $columns[] = ['type' => 'total', 'key' => 'total', 'label' => 'TOTAL NETO'];

        $this->totalCols = count($columns);

        // 8. Sort departments: numeric first (by number), then alpha
        $deptRows = $deptRows->sortBy(function ($d) {
            return self::sortDeptKey($d['department']);
        })->values();

        // 9. Build header labels
        $headers = array_column($columns, 'label');

        // 10. Build data rows
        $dataRows = $deptRows->map(function ($dept) use ($columns) {
            $row = [];
            $total = 0.0;

            foreach ($columns as $col) {
                switch ($col['type']) {
                    case 'dept':
                        $row[] = $dept['department'];
                        break;
                    case 'name':
                        $row[] = $dept['responsible'];
                        break;
                    case 'period':
                        // Aggregate all amounts from this previous year
                        $year = (int) substr($col['key'], 5);
                        $sum = 0.0;
                        foreach ($dept['amounts'] as $k => $v) {
                            if ((int) substr($k, 0, 4) === $year) {
                                $sum += $v;
                            }
                        }
                        $row[] = round($sum, 2) ?: null;
                        $total += $sum;
                        break;
                    case 'month':
                        $amount = $dept['amounts'][$col['key']] ?? 0;
                        $row[] = round($amount, 2) ?: null;
                        $total += $amount;
                        break;
                    case 'total':
                        $row[] = round($total, 2);
                        break;
                    default:
                        $row[] = null;
                }
            }

            return $row;
        })->toArray();

        // 11. Build total row (column sums)
        $totalRow = [];
        $grandTotal = 0.0;
        foreach ($columns as $colIdx => $col) {
            if ($col['type'] === 'dept') {
                $totalRow[] = 'TOTAL GENERAL';
            } elseif ($col['type'] === 'name') {
                $totalRow[] = '';
            } else {
                $sum = 0.0;
                foreach ($dataRows as $dRow) {
                    $val = $dRow[$colIdx] ?? null;
                    if (is_numeric($val)) {
                        $sum += $val;
                    }
                }
                $totalRow[] = round($sum, 2);
                if ($col['type'] !== 'total') {
                    $grandTotal += $sum;
                }
            }
        }

        // 12. Assemble all rows
        $this->dataStartRow = 5; // data starts after 3 title rows + 1 header row
        $this->dataEndRow = 4 + count($dataRows);

        $this->allRows = array_merge(
            [['REPORTE DE MOROSIDAD']],                     // Row 1
            [['MANTENIMIENTO SALDO NETO PENDIENTE']],      // Row 2
            [array_fill(0, $this->totalCols, null)],       // Row 3 (empty)
            [$headers],                                     // Row 4 (headers)
            $dataRows,                                      // Rows 5..N (data)
            [$totalRow],                                    // Row N+1 (total)
        );
    }

    private function buildEmptyResult(): void
    {
        $this->totalCols = 3;
        $this->allRows = [
            ['REPORTE DE MOROSIDAD'],
            ['MANTENIMIENTO SALDO NETO PENDIENTE'],
            [null, null, null],
            ['DEPARTAMENTO', 'PROPIETARIO', 'TOTAL NETO'],
            ['TOTAL GENERAL', '', 0],
        ];
        $this->dataStartRow = 5;
        $this->dataEndRow = 5;
    }

    private static function sortDeptKey(string $dept): array
    {
        if (is_numeric($dept)) {
            return [0, (int) $dept, ''];
        }

        return [1, 0, strtoupper($dept)];
    }

    private static function getColumnLetter(int $column): string
    {
        $letter = '';

        while ($column > 0) {
            $column--;
            $letter = chr(65 + ($column % 26)).$letter;
            $column = intdiv($column, 26);
        }

        return $letter;
    }
}
