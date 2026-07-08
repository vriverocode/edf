<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BookingsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    private array $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query(): Builder
    {
        return Booking::with(['user', 'departament', 'comunArea', 'pay'])
            ->filter($this->filters)
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'N° Reserva',
            'Usuario',
            'Email',
            'Departamento',
            'Área Común',
            'Fecha',
            'Hora Inicio',
            'Hora Fin',
            'Monto',
            'Estado',
            'Estado Pago',
            'Nota',
            'Creado',
        ];
    }

    public function map($booking): array
    {
        $payStatus = match (true) {
            ! $booking->pay => 'Sin pago',
            (int) $booking->pay->status === 0 => 'Anulado',
            (int) $booking->pay->status === 1 => 'Pendiente',
            (int) $booking->pay->status === 2 => 'Aprobado',
            (int) $booking->pay->status === 3 => 'Exitoso',
            default => '—',
        };

        return [
            $booking->booking_number ?? '—',
            $booking->user?->name ?? '—',
            $booking->user?->email ?? '—',
            $booking->departament?->number ?? '—',
            $booking->comunArea?->name ?? '—',
            $booking->date?->format('d/m/Y'),
            $booking->time_from,
            $booking->time_to,
            (float) $booking->amount,
            $booking->status_label,
            $payStatus,
            $booking->note ?? '—',
            $booking->created_at?->format('d/m/Y H:i'),
        ];
    }
}
