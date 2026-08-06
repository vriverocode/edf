<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    // Evitar "Magic Numbers" en tu aplicación
    public const STATUS_CANCELLED = 0;

    public const STATUS_PENDING_PAY = 1;

    public const STATUS_PENDING_APPROVAL = 2;

    public const STATUS_SUCCESS = 3;

    public const STATUS_COMPLETED = 4;

    public const STATUS_PENDING_REFUND = 5;

    public const STATUS_PENDING_DEVO = 6;

    protected $fillable = [
        'user_id', 'departament_id', 'comun_area_id', 'booking_number',
        'date', 'time_from', 'time_to', 'amount', 'type',
        'note', 'motive', 'status', 'kind', 'pending_pay_notification_sent_at', 'is_exclusive',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'pending_pay_notification_sent_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    // Corregido: status_color estaba duplicado
    public $appends = ['booking_hour', 'status_label', 'status_color', 'type_label', 'type_color', 'has_extension', 'kind_label'];

    /* -------------------------------------------------------------------------- */
    /* Relaciones */
    /* -------------------------------------------------------------------------- */
    public function comunArea(): BelongsTo
    {
        return $this->belongsTo(ComunArea::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function departament(): BelongsTo
    {
        return $this->belongsTo(Departament::class);
    }

    public function pay(): HasOne
    {
        return $this->hasOne(Pay::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /* -------------------------------------------------------------------------- */
    /* Accessors (Sintaxis Moderna Laravel 9+) */
    /* -------------------------------------------------------------------------- */
    protected function bookingHour(): Attribute
    {
        return Attribute::make(
            get: fn () => Carbon::parse($this->time_to)->diffInHours(Carbon::parse($this->time_from))
        );
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ((int) $this->status) {
                self::STATUS_CANCELLED => 'Cancelada',
                self::STATUS_PENDING_PAY => 'Pago pendiente',
                self::STATUS_PENDING_APPROVAL => 'Pendiente de aprob.',
                self::STATUS_SUCCESS => 'Exitoso',
                self::STATUS_COMPLETED => 'Completada',
                self::STATUS_PENDING_REFUND => 'Pend. reembolso',
                self::STATUS_PENDING_DEVO => 'Pend. devolución',
                default => 'Desconocido',
            }
        );
    }

    protected function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ((int) $this->status) {
                self::STATUS_CANCELLED => 'negative',
                self::STATUS_PENDING_PAY, self::STATUS_PENDING_APPROVAL => 'warning',
                self::STATUS_SUCCESS => 'positive',
                self::STATUS_COMPLETED => 'teal-8',
                self::STATUS_PENDING_REFUND => 'orange-8',
                self::STATUS_PENDING_DEVO => 'orange-8',
                default => 'grey',
            }
        );
    }

    protected function typeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ((int) $this->type) {
                0 => 'Cancelada',
                1 => 'Compartida',
                2 => 'Exclusiva',
                3 => 'De pago',
                4 => 'Extension',

                default => 'Desconocido',
            }
        );
    }

    protected function typeColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ((int) $this->type) {
                1 => 'blue-9',
                2 => 'deep-purple-10',
                3 => 'light-green-13',
                4 => 'amber-8',
                default => 'No definido',
            }
        );
    }

    protected function kindLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->kind) {
                'warranty' => 'Garantía',
                'cancellation' => 'Cancelación',
                default => null,
            }
        );
    }

    protected function hasExtension(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->type != 4 && Booking::where('type', 4)
                ->where('comun_area_id', $this->comun_area_id)
                ->where('date', $this->date)
                ->where('user_id', $this->user_id)
                ->where('status', '>', 0)
                ->where('note', 'like', '%'.$this->booking_number.'%')
                ->exists()
        );
    }

    /* -------------------------------------------------------------------------- */
    /* Query Scopes (Filtros extraídos del Controlador) */
    /* -------------------------------------------------------------------------- */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->when(isset($filters['status']) && (int) $filters['status'] !== -1, fn ($q) => $q->where('status', (int) $filters['status']))
            ->when($filters['area_id'] ?? null, fn ($q, $areaId) => $q->where('comun_area_id', (int) $areaId))
            ->when($filters['date_from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($filters['amount_type'] ?? null, function ($q, $type) {
                if ($type === 'free') {
                    $q->where('amount', 0);
                }
                if ($type === 'paid') {
                    $q->where('amount', '>', 0);
                }
            });

        $sortBy = in_array($filters['sort_by'] ?? '', ['created_at', 'date', 'status', 'amount'])
            ? $filters['sort_by']
            : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDir);
    }
}
