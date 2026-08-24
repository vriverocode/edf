<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DelinquentsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    private Collection $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'DNI',
            'Email',
            'Teléfono',
            'Departamentos',
            'Tipo Morosidad',
            'Deuda Total',
            'N° Cuotas',
        ];
    }

    public function map($row): array
    {
        $typeLabels = [
            'user_status' => 'Moroso (estado)',
            'overdue_quotas' => 'Cuotas >2 meses',
        ];
        $types = collect($row['types'] ?? [])
            ->map(fn ($t) => $typeLabels[$t] ?? $t)
            ->implode(' · ');

        $departments = collect($row['departments'] ?? [])->implode(', ');

        return [
            $row['name'] ?? '—',
            $row['dni'] ?? '—',
            $row['email'] ?? '—',
            $row['phone'] ?? '—',
            $departments ?: '—',
            $types ?: '—',
            (float) ($row['total_debt'] ?? 0),
            (int) ($row['quotas_count'] ?? 0),
        ];
    }
}
