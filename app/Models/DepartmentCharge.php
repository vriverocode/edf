<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepartmentCharge extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'departament_id',
        'expense_id',
        'description',
        'total_amount',
        'monthly_amount',
        'installments',
        'paid_installments',
        'status',
        'start_month',
        'start_year',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'monthly_amount' => 'float',
        'installments' => 'integer',
        'paid_installments' => 'integer',
        'start_month' => 'integer',
        'start_year' => 'integer',
    ];

    public $appends = ['status_label', 'status_color', 'remaining_installments'];

    public function departament(): BelongsTo
    {
        return $this->belongsTo(Departament::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function quotas(): BelongsToMany
    {
        return $this->belongsToMany(Quota::class, 'charge_quota')->withPivot('installment_number');
    }

    public function getRemainingInstallmentsAttribute(): int
    {
        return $this->installments - $this->paid_installments;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            1 => 'Activo',
            2 => 'Pagado',
            3 => 'Cancelado',
            default => '—',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            1 => 'warning',
            2 => 'positive',
            3 => 'negative',
            default => 'grey',
        };
    }

    public function isFinished(): bool
    {
        return $this->paid_installments >= $this->installments;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1)->whereRaw('paid_installments < installments');
    }

    public function scopeApplicableForPeriod($query, int $month, int $year)
    {
        return $query->where('status', 1)
            ->whereRaw('(start_year * 12 + start_month) <= ? * 12 + ?', [$year, $month])
            ->whereRaw('paid_installments < installments');
    }
}
