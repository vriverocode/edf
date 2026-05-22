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
        "type",
        "amount",
        "vaucher",
        "reference",
        "pay_date",
        "pay_id",
        "pay_method",
        "status"
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
    public function transactions(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }
    public function getStatusLabelAttribute()
    {
        $status = [
            "Cancelada",
            "Pendiente de aprob.",
            "Exitoso"
        ];
        return  $status[$this->status];
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
            "positive"
        ];
        return  $status[$this->status];
    }
    public function getStatusIconAttribute()
    {
        $status = [
            "eva-close-outline",
            "eva-alert-circle-outline",
            "eva-checkmark-outline"
        ];
        return  $status[$this->status];
    }
}
