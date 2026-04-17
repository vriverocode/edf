<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    //
    use SoftDeletes;

    protected $fillable = [
        "user_id",
        "comun_area_id",
        "booking_number",
        "date",
        "time_from",
        "time_to",
        "amount",
        "type",
        "note",
        "status",
        "is_exclusive"
    ];
    public $appends  =   ["booking_hour", "status_label", "status_color", "status_color", 'type_label', "type_color"];

    public function comunArea(): BelongsTo
    {
        return $this->belongsTo(ComunArea::class, 'comun_area_id', 'id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function pay(): HasOne
    {
        return $this->hasOne(Pay::class);
    }
    public function getBookingHourAttribute()
    {
        $hour = intval(substr($this->time_to, 0, 2)) - intval(substr($this->time_from, 0, 2));
        return  $hour;
    }
    public function getStatusLabelAttribute()
    {
        $status = [
            "Cancelada",
            "Pago pendiente",
            "Pendiente de aprob.",
            "Exitoso"
        ];
        return  $status[$this->status];
    }
    public function getTypeLabelAttribute()
    {
        $types = [
            "Cancelada",
            "Compartida",
            "Exclusiva",
            "De pago"
        ];
        return  $types[$this->type];
    }
    public function getTypeColorAttribute()
    {
        return match((int) $this->type) {
            1 => 'blue-9',
            2 => 'deep-purple-10',
            3 => 'light-green-13',
            4 => 'De pago lista de invitados',
            default => 'No definido',
        };
    }
    public function getStatusColorAttribute()
    {
        $color = [
            "negative",
            "warning",
            "warning",
            "positive"
        ];
        return  $color[$this->status];
    }
}
