<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonthlyPaymentsExport implements FromCollection, WithColumnWidths, WithEvents, WithMapping, WithStyles
{
    private array $departments;

    private array $totals;

    private int $year;

    private int $rowIndex = 0;

    private array $months = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    private const STATUS_PAID = 3;

    private const STATUS_OVERDUE = 4;

    private const COLOR_GREEN_BG = 'E8F5E9';

    private const COLOR_GREEN_FONT = '2E7D32';

    private const COLOR_YELLOW_BG = 'FFF8E1';

    private const COLOR_YELLOW_FONT = 'F57F17';

    private const COLOR_RED_BG = 'FFEBEE';

    private const COLOR_RED_FONT = 'C62828';

    private const COLOR_BLUE_BG = 'E3F2FD';

    private const COLOR_BLUE_FONT = '1565C0';

    private const COLOR_GRAY_BG = 'FAFAFA';

    private const COLOR_GRAY_FONT = 'BDBDBD';

    private const COLOR_HEADER_BG = 'F5F5F5';

    private const COLOR_HEADER_FONT = '666666';

    public function __construct(array $departments, array $totals, int $year)
    {
        $this->departments = $departments;
        $this->totals = $totals;
        $this->year = $year;
    }

    public function collection()
    {
        return collect($this->departments);
    }

    public function map($dept): array
    {
        $this->rowIndex++;

        $row = [
            strtoupper($dept['number']),
            $dept['type_label'] ?? '—',
            $dept['responsible'] ?? '—',
        ];

        foreach ($this->months as $num => $name) {
            $m = $dept['months'][$num] ?? null;
            $row[] = $m ? round($m['amount'], 2) : null;
        }

        return $row;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'N'; // A=Unidad, B=Tipo, C=Responsable, D-N=Ene-Dic (12 months)
                $dataStartRow = 7; // after title + blank + header + 3 summary rows + blank
                $dataEndRow = $dataStartRow + count($this->departments) - 1;

                // ── Title row (row 1) ──
                $sheet->mergeCells('A1:'.$lastCol.'1');
                $sheet->setCellValue('A1', 'REPORTE DE PAGOS MENSUALES - '.$this->year);
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'name' => 'Calibri', 'color' => ['rgb' => '333333']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(35);

                // ── Header row (row 3) ──
                $headerRow = 3;
                $headers = array_merge(['Unidad', 'Tipo', 'Responsable'], array_values($this->months));
                $col = 'A';
                foreach ($headers as $i => $h) {
                    $cell = $col.$headerRow;
                    $sheet->setCellValue($cell, strtoupper($h));
                    $col = $this->nextColumn($col);
                }
                $sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => self::COLOR_HEADER_FONT]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER_BG]],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(25);

                // ── Summary rows at top (rows 4-6) ──
                $this->addSummaryRow($sheet, 4, 'COBRADO', 'paid', self::COLOR_GREEN_BG, self::COLOR_GREEN_FONT);
                $this->addSummaryRow($sheet, 5, 'PENDIENTE', 'pending', self::COLOR_YELLOW_BG, self::COLOR_YELLOW_FONT);
                $this->addSummaryRow($sheet, 6, 'TOTAL', 'amount', self::COLOR_BLUE_BG, self::COLOR_BLUE_FONT);

                // ── Data rows styling ──
                if (count($this->departments) > 0) {
                    $dataRange = 'A'.$dataStartRow.':'.$lastCol.$dataEndRow;
                    $sheet->getStyle($dataRange)->applyFromArray([
                        'font' => ['size' => 10, 'name' => 'Calibri'],
                        'alignment' => ['vertical' => 'center'],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
                    ]);

                    // Style each data cell based on status
                    $rowNum = $dataStartRow;
                    foreach ($this->departments as $dept) {
                        // Fixed columns: A (Unidad), B (Tipo), C (Responsable)
                        $sheet->getStyle('A'.$rowNum)->applyFromArray([
                            'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri'],
                            'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
                        ]);
                        $sheet->getStyle('B'.$rowNum)->applyFromArray([
                            'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
                        ]);
                        $sheet->getStyle('C'.$rowNum)->applyFromArray([
                            'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
                        ]);

                        // Month cells (D-N)
                        $col = 'D';
                        foreach ($this->months as $num => $name) {
                            $m = $dept['months'][$num] ?? null;
                            $cell = $col.$rowNum;

                            if ($m) {
                                $sheet->setCellValue($cell, round($m['amount'], 2));
                                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('S/. #,##0.00');

                                $status = $m['status'];
                                if ($status === self::STATUS_PAID) {
                                    $bg = self::COLOR_GREEN_BG;
                                    $font = self::COLOR_GREEN_FONT;
                                } elseif ($status === self::STATUS_OVERDUE) {
                                    $bg = self::COLOR_RED_BG;
                                    $font = self::COLOR_RED_FONT;
                                } else {
                                    $bg = self::COLOR_YELLOW_BG;
                                    $font = self::COLOR_YELLOW_FONT;
                                }

                                $sheet->getStyle($cell)->applyFromArray([
                                    'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => $font]],
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                                ]);
                            } else {
                                $sheet->setCellValue($cell, '—');
                                $sheet->getStyle($cell)->applyFromArray([
                                    'font' => ['size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => self::COLOR_GRAY_FONT]],
                                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_GRAY_BG]],
                                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                                ]);
                            }
                            $col = $this->nextColumn($col);
                        }
                        $rowNum++;
                    }
                }

                // ── Footer summary rows (3 rows at bottom) ──
                $footerStart = $dataEndRow + 2;
                $this->addSummaryRow($sheet, $footerStart, 'COBRADO', 'paid', self::COLOR_GREEN_BG, self::COLOR_GREEN_FONT);
                $this->addSummaryRow($sheet, $footerStart + 1, 'PENDIENTE', 'pending', self::COLOR_YELLOW_BG, self::COLOR_YELLOW_FONT);
                $this->addSummaryRow($sheet, $footerStart + 2, 'TOTAL', 'amount', self::COLOR_BLUE_BG, self::COLOR_BLUE_FONT);
            },
        ];
    }

    private function addSummaryRow(Worksheet $sheet, int $rowNum, string $label, string $key, string $bgColor, string $fontColor): void
    {
        $lastCol = 'N';
        $sheet->setCellValue('A'.$rowNum, $label);
        $sheet->setCellValue('B'.$rowNum, '');
        $sheet->setCellValue('C'.$rowNum, '');

        $col = 'D';
        foreach ($this->months as $num => $name) {
            $value = $this->totals[$num][$key] ?? 0;
            $cell = $col.$rowNum;
            $sheet->setCellValue($cell, round($value, 2));
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('S/. #,##0.00');
            $col = $this->nextColumn($col);
        }

        $sheet->getStyle('A'.$rowNum.':'.$lastCol.$rowNum)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => $fontColor]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);

        // Label column left-aligned
        $sheet->getStyle('A'.$rowNum)->applyFromArray([
            'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
        ]);

        $sheet->getRowDimension($rowNum)->setRowHeight(25);
    }

    private function nextColumn(string $col): string
    {
        return ++$col;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 16,
            'C' => 22,
            'D' => 14,
            'E' => 14,
            'F' => 14,
            'G' => 14,
            'H' => 14,
            'I' => 14,
            'J' => 14,
            'K' => 14,
            'L' => 14,
            'M' => 14,
            'N' => 14,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }
}
