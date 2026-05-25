<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pay extends Model
{
    //
    use SoftDeletes;

    protected $fillable = [
        "user_id",
        "booking_id",
        "quota_id",
        'consolidated_ids',
        "type",
        "amount",
        "vaucher",
        "reference",
        "pay_date",
        "pay_id",
        "pay_method",
        "status"
    ];

    protected $casts = [
        'consolidated_ids' => 'array',
    ];
    public $appends  =   ["status_label", "status_color", "status_icon", "title_pay"];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }
    public function quotas(): BelongsToMany
    {
        return $this->belongsToMany(Quota::class, 'pay_quota');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function payMethod(): BelongsTo
    {
        return $this->belongsTo(PayMethod::class, 'pay_method');
    }
    public function financialTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    /** IDs de cuotas incluidas en un pago global (o ítem único cuando no hay JSON). */
    public function consolidatedQuotaIds(): array
    {
        $ids = $this->consolidated_ids;
        if (! is_array($ids) || empty($ids)) {
            return $this->quota_id ? [(int) $this->quota_id] : [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
    public function getStatusLabelAttribute()
    {
        $status = [
            "Cancelada",
            "Pendiente de aprob.",
            "Exitoso",
            "Rechazado",
        ];
        return $status[$this->status] ?? '—';
    }
    public function getTitlePayAttribute()
    {
        $payMethod = [
            '',
            "Pago de quota",
            "Pago de Reserva",
        ];
        return  $payMethod[$this->type];
    }
    public function getStatusColorAttribute()
    {
        $status = [
            "negative",
            "warning",
            "positive",
            "negative",
        ];
        return $status[$this->status] ?? 'grey';
    }
    public function getStatusIconAttribute()
    {
        $status = [
            "eva-close-outline",
            "eva-alert-circle-outline",
            "eva-checkmark-outline",
            "eva-slash-outline",
        ];
        return $status[$this->status] ?? 'eva-question-mark-outline';
    }
}
