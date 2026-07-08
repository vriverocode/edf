<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $appends = ['status_label', 'expense_type_label'];

    protected $fillable = [
        'provider_id',
        'monthly_bill_id',
        'invoice_number',
        'amount',
        'issue_date',
        'due_date',
        'expense_type',
        'location_scope',
        'unit',
        'description',
        'attachment_url',
        'status',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function monthlyBill()
    {
        return $this->belongsTo(MonthlyBills::class, 'monthly_bill_id');
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            1 => 'Pendiente',
            2 => 'Aprobado para pago',
            3 => 'Pagado',
        ];

        return $labels[$this->status] ?? '—';
    }

    public function getExpenseTypeLabelAttribute(): string
    {
        $labels = [
            1 => 'Ordinario / Recurrente',
            2 => 'Extraordinario',
        ];

        return $labels[$this->expense_type] ?? '—';
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['year'] ?? null, function ($q, $year) {
                $q->whereYear('issue_date', $year);
            })
            ->when($filters['month'] ?? null, function ($q, $month) {
                $q->whereMonth('issue_date', $month);
            })
            ->when(isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
    }
}
