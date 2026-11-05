<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Multa extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'rule_id',
        'departament_id',
        'type',
        'description',
        'incident_date',
        'amount',
        'installments',
        'paid_installments',
        'start_month',
        'start_year',
        'pay_id',
        'status',
    ];

    protected $casts = [
        'amount' => 'float',
        'installments' => 'integer',
        'paid_installments' => 'integer',
        'start_month' => 'integer',
        'start_year' => 'integer',
        'type' => 'integer',
        'status' => 'integer',
    ];

    public $appends = ['status_label', 'status_color', 'monthly_amount', 'remaining_installments'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }

    public function departament(): BelongsTo
    {
        return $this->belongsTo(Departament::class, 'departament_id', 'id');
    }

    // Alias mantenido por compatibilidad histórica
    public function user(): BelongsTo
    {
        return $this->departament();
    }

    public function quotas(): BelongsToMany
    {
        return $this->belongsToMany(Quota::class, 'multa_quota')->withPivot('installment_number');
    }

    public function getMonthlyAmountAttribute(): float
    {
        $inst = max(1, (int) ($this->installments ?? 1));

        return round(((float) $this->amount) / $inst, 2);
    }

    public function getRemainingInstallmentsAttribute(): int
    {
        return max(0, ((int) ($this->installments ?? 1)) - ((int) ($this->paid_installments ?? 0)));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((int) $this->status) {
            1 => 'Pendiente / Activo',
            2 => 'Pagado',
            3 => 'Anulado',
            default => '—',
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ((int) $this->status) {
            1 => 'warning',
            2 => 'positive',
            3 => 'negative',
            default => 'grey',
        };
    }

    public function isFinished(): bool
    {
        return (int) $this->paid_installments >= (int) max(1, $this->installments);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1)->whereRaw('paid_installments < installments');
    }

    public function scopeApplicableForPeriod($query, int $month, int $year)
    {
        return $query->where('status', 1)
            ->whereNotNull('start_month')
            ->whereNotNull('start_year')
            ->whereRaw('(start_year * 12 + start_month) <= ? * 12 + ?', [$year, $month])
            ->whereRaw('paid_installments < installments');
    }
}
