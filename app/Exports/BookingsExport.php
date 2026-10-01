<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookingsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    private const DAY_NAMES = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    ];

    private array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        $query = Booking::with(['user.rol', 'departament', 'comunArea', 'pay'])
            ->filter($this->filters);

        $hasStatusFilter = isset($this->filters['status']) && (int) $this->filters['status'] !== -1;

        if (! ($this->filters['include_cancelled'] ?? false) && ! $hasStatusFilter) {
            $query->where('status', '>', 0);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Día',
            'Fec. Uso',
            'Horario',
            'Amb. Común',
            'Dpto',
            'Fec. Registro',
            'Usuario',
            'Tipo',
            'Cod Pago',
            'Total',
            'Fec. Pago',
            'Estado',
        ];
    }

    public function map($booking): array
    {
        $horario = collect([$booking->time_from, $booking->time_to])
            ->filter()
            ->implode(' - ');

        return [
            $booking->date ? (self::DAY_NAMES[(int) $booking->date->dayOfWeek] ?? '—') : '—',
            $booking->date ? $booking->date->format('d/m/Y') : '—',
            $horario,
            $booking->comunArea?->name ?? '—',
            $booking->departament?->number ?? '—',
            $booking->created_at?->format('d/m/Y') ?? '—',
            $booking->user?->name ?? '—',
            $booking->user?->rol?->name ?? '—',
            $booking->booking_number ?? '—',
            (float) $booking->amount,
            $booking->pay?->pay_date ? Carbon::parse($booking->pay->pay_date)->format('d/m/Y') : '—',
            $booking->status_label,
        ];
    }
}
