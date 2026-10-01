<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserQuotasExport implements FromCollection, WithColumnWidths, WithEvents, WithStyles
{
    private array $rows;

    private array $totals;

    private int $year;

    private int $month;

    private const COLOR_BLUE_BG = 'E3F2FD';

    private const COLOR_BLUE_FONT = '1565C0';

    private const COLOR_HEADER_BG = 'F5F5F5';

    private const COLOR_HEADER_FONT = '666666';

    private const HEADERS = [
        'Usuario',
        'DPT',
        '% DPT',
        'EST',
        '% EST',
        'DPO',
        '% DPO',
        '% Total',
        'Consumo agua (m³)',
        'Agua en cuotas',
        'Mantenimiento',
        'Descuento',
        'Total',
    ];

    private const MONTH_NAMES = [
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

    public function __construct(array $rows, array $totals, int $year, int $month)
    {
        $this->rows = $rows;
        $this->totals = $totals;
        $this->year = $year;
        $this->month = $month;
    }

    /**
     * Los datos de Laravel Excel empiezan en la fila 1, así que el preludio
     * (título, fila en blanco y cabecera) viaja aquí para que las filas de
     * datos caigan en la 4 y no se pisen con el título/cabecera.
     */
    public function collection()
    {
        $rows = [];

        $title = array_fill(0, count(self::HEADERS), null);
        $title[0] = 'REPORTE DE CUOTAS POR USUARIO - '.self::MONTH_NAMES[$this->month].' '.$this->year;
        $rows[] = $title;
        $rows[] = array_fill(0, count(self::HEADERS), null);
        $rows[] = self::HEADERS;

        foreach ($this->rows as $row) {
            $rows[] = $this->buildRow($row);
        }

        return collect($rows);
    }

    private function buildRow($row): array
    {
        return [
            strtoupper((string) $row['user_name']),
            $row['dpts'] !== '' ? $row['dpts'] : '—',
            $this->formatPct((float) $row['dpt_pct']),
            $row['ests'] !== '' ? $row['ests'] : '—',
            $this->formatPct((float) $row['est_pct']),
            $row['dpos'] !== '' ? $row['dpos'] : '—',
            $this->formatPct((float) $row['dpo_pct']),
            $this->formatPct((float) $row['pct_total']),
            round((float) $row['water_consumption'], 3),
            round((float) $row['water_in_quotas'], 2),
            round((float) $row['maintenance'], 2),
            round((float) $row['discount'], 2),
            round((float) $row['total'], 2),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'M'; // A=Usuario, B-M=DPT..Total (13 cols)
                $dataStartRow = 4;
                $dataEndRow = $dataStartRow + count($this->rows) - 1;

                // ── Title row (row 1) ──
                $sheet->mergeCells('A1:'.$lastCol.'1');
                $sheet->setCellValue('A1', 'REPORTE DE CUOTAS POR USUARIO - '.self::MONTH_NAMES[$this->month].' '.$this->year);
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'name' => 'Calibri', 'color' => ['rgb' => '333333']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(35);

                // ── Header row (row 3) ──
                $headerRow = 3;
                $col = 'A';
                foreach (self::HEADERS as $h) {
                    $sheet->setCellValue($col.$headerRow, strtoupper($h));
                    $col = $this->nextColumn($col);
                }
                $sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => self::COLOR_HEADER_FONT]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER_BG]],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(30);

                // ── Data rows styling ──
                if (count($this->rows) > 0) {
                    $sheet->getStyle('A'.$dataStartRow.':'.$lastCol.$dataEndRow)->applyFromArray([
                        'font' => ['size' => 10, 'name' => 'Calibri'],
                        'alignment' => ['vertical' => 'center'],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
                    ]);

                    $rowNum = $dataStartRow;
                    foreach ($this->rows as $row) {
                        $sheet->getStyle('A'.$rowNum)->applyFromArray([
                            'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri'],
                            'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
                        ]);

                        // Unidades (B, D, F) alineadas a la izquierda
                        foreach (['B', 'D', 'F'] as $unitCol) {
                            $sheet->getStyle($unitCol.$rowNum)->applyFromArray([
                                'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
                            ]);
                        }

                        // Porcentajes (C, E, G, H) centrados
                        foreach (['C', 'E', 'G', 'H'] as $pctCol) {
                            $sheet->getStyle($pctCol.$rowNum)->applyFromArray([
                                'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
                            ]);
                        }

                        // Consumo de agua en m³ (I)
                        $sheet->getStyle('I'.$rowNum)->getNumberFormat()->setFormatCode('0.000');
                        // Montos (J-M)
                        $sheet->getStyle('J'.$rowNum.':M'.$rowNum)->getNumberFormat()->setFormatCode('"S/." #,##0.00');

                        // Total (M) en negrita
                        $sheet->getStyle('M'.$rowNum)->applyFromArray([
                            'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri'],
                        ]);

                        $rowNum++;
                    }
                }

                // ── Footer totals row ──
                $this->addTotalsRow($sheet, $dataEndRow + 2, $lastCol);
            },
        ];
    }

    private function addTotalsRow(Worksheet $sheet, int $rowNum, string $lastCol): void
    {
        $sheet->setCellValue('A'.$rowNum, 'TOTAL');
        $sheet->setCellValue('B'.$rowNum, '');
        $sheet->setCellValue('C'.$rowNum, '');
        $sheet->setCellValue('D'.$rowNum, '');
        $sheet->setCellValue('E'.$rowNum, '');
        $sheet->setCellValue('F'.$rowNum, '');
        $sheet->setCellValue('G'.$rowNum, '');
        $sheet->setCellValue('H'.$rowNum, $this->formatPct((float) ($this->totals['pct_total'] ?? 0)));

        $sheet->setCellValue('I'.$rowNum, round((float) ($this->totals['water_consumption'] ?? 0), 3));
        $sheet->getStyle('I'.$rowNum)->getNumberFormat()->setFormatCode('0.000');

        $money = [
            'J' => 'water_in_quotas',
            'K' => 'maintenance',
            'L' => 'discount',
            'M' => 'total',
        ];
        foreach ($money as $col => $key) {
            $sheet->setCellValue($col.$rowNum, round((float) ($this->totals[$key] ?? 0), 2));
            $sheet->getStyle($col.$rowNum)->getNumberFormat()->setFormatCode('"S/." #,##0.00');
        }

        $sheet->getStyle('A'.$rowNum.':'.$lastCol.$rowNum)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'name' => 'Calibri', 'color' => ['rgb' => self::COLOR_BLUE_FONT]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_BLUE_BG]],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
        $sheet->getStyle('A'.$rowNum)->applyFromArray([
            'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
        ]);
        $sheet->getRowDimension($rowNum)->setRowHeight(25);
    }

    private function formatPct(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 5, '.', ''), '0'), '.');

        if ($formatted === '' || $formatted === '-') {
            $formatted = '0';
        }

        return '%'.$formatted;
    }

    private function nextColumn(string $col): string
    {
        return ++$col;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 30,
            'C' => 11,
            'D' => 24,
            'E' => 11,
            'F' => 16,
            'G' => 11,
            'H' => 11,
            'I' => 17,
            'J' => 16,
            'K' => 16,
            'L' => 16,
            'M' => 16,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }
}
