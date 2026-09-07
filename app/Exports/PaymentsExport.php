<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Pay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithRowHeights;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentsExport implements FromCollection, WithColumnWidths, WithHeadings, WithMapping, WithRowHeights, WithStyles
{
    private array $filters;

    private int $rowIndex = 2;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function collection(): Collection
    {
        $query = Pay::with(['user', 'quotas.departament', 'payMethod'])
            ->where('type', 1);

        if (! empty($this->filters['date_from'])) {
            $query->whereDate('pay_date', '>=', Carbon::createFromFormat('d/m/Y', $this->filters['date_from'])->format('Y-m-d'));
        }
        if (! empty($this->filters['date_to'])) {
            $query->whereDate('pay_date', '<=', Carbon::createFromFormat('d/m/Y', $this->filters['date_to'])->format('Y-m-d'));
        }
        if (! empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('quotas.departament', fn ($dq) => $dq->where('number', 'like', '%'.$search.'%'));
            });
        }
        if (isset($this->filters['status']) && intval($this->filters['status']) !== -1) {
            $query->where('status', intval($this->filters['status']));
        }

        $sortBy = $this->filters['sort_by'] ?? 'pay_date';
        $sortDir = ($this->filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'dept_number') {
            $query->join('pay_quota as pq_sort', 'pays.id', '=', 'pq_sort.pay_id')
                ->join('quotas as q_sort', 'q_sort.id', '=', 'pq_sort.quota_id')
                ->join('departaments as d_sort', 'd_sort.id', '=', 'q_sort.departament_id')
                ->orderBy('d_sort.number', $sortDir)
                ->orderBy('pays.id', $sortDir);
        } elseif ($sortBy === 'month') {
            $query->join('pay_quota as pq_sort2', 'pays.id', '=', 'pq_sort2.pay_id')
                ->join('quotas as q_sort2', 'q_sort2.id', '=', 'pq_sort2.quota_id')
                ->orderBy('q_sort2.month', $sortDir)
                ->orderBy('pays.id', $sortDir);
        } else {
            $validSortFields = ['pay_date', 'amount', 'status'];
            $safeSortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'pay_date';
            $query->orderBy($safeSortBy, $sortDir)->orderBy('id', $sortDir);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Usuario',
            'Departamento',
            'Cuota',
            'Monto',
            'Método de pago',
            'Referencia',
            'Estado',
        ];
    }

    public function map($pay): array
    {
        $this->rowIndex++;

        return [
            Carbon::parse($pay->pay_date)?->format('d/m/Y'),
            $pay->user?->name ?? '—',
            $pay->quotas->first()?->departament?->number ?? '—',
            $pay->quotas->first()?->month_label ?? '—',
            round((float) $pay->amount, 2),
            $pay->payMethod?->name ?? '—',
            $pay->reference ?? '—',
            $pay->status_label,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastColumn = 'H';
        $lastRow = $this->rowIndex;

        return [
            // Header row
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11,
                    'name' => 'Calibri',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2D6FB5'],
                ],
                'alignment' => [
                    'horizontal' => 'center',
                    'vertical' => 'center',
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ],
            // Data rows
            '2:'.$lastRow => [
                'font' => [
                    'size' => 10,
                    'name' => 'Calibri',
                ],
                'alignment' => [
                    'vertical' => 'center',
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E0E0E0'],
                    ],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,  // Fecha
            'B' => 28,  // Usuario
            'C' => 16,  // Departamento
            'D' => 14,  // Cuota
            'E' => 14,  // Monto
            'F' => 20,  // Método de pago
            'G' => 20,  // Referencia
            'H' => 22,  // Estado
        ];
    }

    public function rowHeights(): array
    {
        return [
            1 => 30,  // Header row height
        ];
    }
}
