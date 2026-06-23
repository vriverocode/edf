<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Quota extends Model
{
    use SoftDeletes;

    protected $fillable = [
        "departament_id",
        "water_reading_id",
        "maintenance_amount",
        "water_amount",
        "amount",
        "number",
        "month",
        "due_date",
        "type",
        "description",
        "status",
    ];

    public $appends = ["status_label", "status_color", "status_icon", "month_label"];

    public function departament(): BelongsTo
    {
        return $this->belongsTo(Departament::class, 'departament_id', 'id');
    }

    public function pays(): BelongsToMany
    {
        return $this->belongsToMany(Pay::class, 'pay_quota');
    }

    public function waterReading(): BelongsTo
    {
        return $this->belongsTo(WaterReading::class, 'water_reading_id', 'id');
    }

    public function scopeForMonthYear(Builder $query, int $month, int $year): Builder
    {
        return $query
            ->where('month', $month)
            ->whereYear('due_date', $year);
    }

    public static function baseAdminQuery(): Builder
    {
        return static::query()
            ->with([
                'pays' => function ($query) {
                    $query->where('status', '!=', 0);
                },
                'departament.owner',
            ])
            ->orderBy('created_at', 'desc');
    }

    public static function aggregateMonthlySummary(Collection $quotas): array
    {
        $ownerIds = $quotas
            ->pluck('departament.user_id')
            ->filter()
            ->unique();

        $totalAmount = (float) $quotas->sum('amount');
        $totalPaid = (float) $quotas->where('status', 3)->sum('amount');
        $totalPending = (float) $quotas->whereIn('status', [1, 2])->sum('amount');

        return [
            'total_amount' => round($totalAmount, 2),
            'total_paid' => round($totalPaid, 2),
            'total_pending' => round($totalPending, 2),
            'units_count' => $quotas->count(),
            'owners_count' => $ownerIds->count(),
        ];
    }

    public static function countPendingQuotaPaymentsForMonth(int $month, int $year): int
    {
        return Pay::query()
            ->where('type', 1)
            ->where('status', 1)
            ->whereHas('quotas', function (Builder $query) use ($month, $year) {
                $query->forMonthYear($month, $year);
            })
            ->count();
    }

    public static function groupConsolidatedByOwner(Collection $quotas): Collection
    {
        return $quotas
            ->groupBy(function ($quota) {
                $userId = $quota->departament->user_id ?? '0';
                $year = $quota->due_date
                    ? Carbon::parse($quota->due_date)->year
                    : now()->year;

                return $userId . '_' . $quota->month . '_' . $year;
            })
            ->map(function ($group) {
                $firstQuota = $group->first();
                $owner = $firstQuota->departament->owner ?? null;
                $pay = $firstQuota->pays->first();
                $payId = $pay?->id;
                $payStatus = $pay !== null ? (int) $pay->status : null;

                // Lógica de jerarquía para el estatus consolidado
                if ($group->contains(fn ($q) => (int) $q->status === 1)) {
                    $status = 1; // 1: Pago pendiente
                } elseif ($group->contains(fn ($q) => (int) $q->status === 2)) {
                    $status = 2; // 2: Pendiente de aprob.
                } elseif ($group->contains(fn ($q) => (int) $q->status === 3)) {
                    $status = 3; // 3: Exitoso
                } else {
                    $status = 0; // 0: Cancelada
                }

                return [
                    'id' => 'group-' . $group->pluck('id')->join('-'),
                    'month' => $firstQuota->month,
                    'due_date' => $firstQuota->due_date,
                    'description' => 'Cuota Consolidada (' . $group->count() . ' unidades asignadas)',
                    'owner_name' => $owner ? $owner->name : 'Desconocido',
                    'owner_id' => $owner?->id,
                    'maintenance_amount' => $group->sum('maintenance_amount'),
                    'water_amount' => $group->sum('water_amount'),
                    'amount' => $group->sum('amount'),
                    'status' => $status, // <--- Estatus calculado implementado aquí
                    'pay' => $payId,
                    'pay_id' => $payId,
                    'pay_status' => $payStatus,
                    'pending_validation' => $payStatus === 1,
                    'details' => $group->values()->all(),
                ];
            })
            ->values();
    }

    public function getStatusLabelAttribute()
    {
        $statusLabels = [
            "Cancelada",
            "Pago pendiente",
            "Pendiente de aprob.",
            "Exitoso",
            "Vencida.",
        ];

        return $statusLabels[$this->status] ?? '—';
    }

    public function getMonthLabelAttribute()
    {
        $months = [
            '',
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Septiembre',
            'Octubre',
            'Noviembre',
            'Diciembre'
        ];

        return $months[$this->month] ?? '';
    }

    public function getStatusColorAttribute()
    {
        $color = [
            "negative",
            "warning",
            "warning",
            "positive"
        ];

        return $color[$this->status] ?? 'grey';
    }

    public function getStatusIconAttribute()
    {
        $status = [
            "eva-close-outline",
            "eva-alert-circle-outline",
            "eva-alert-circle-outline",
            "eva-checkmark-outline"
        ];

        return $status[$this->status] ?? 'eva-question-mark-outline';
    }
}
