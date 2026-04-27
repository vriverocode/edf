<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

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


    public $appends  =   ["status_label", "status_color", "status_icon", "month_label"];

    public function departament(): BelongsTo
    {
        return $this->belongsTo(Departament::class, 'departament_id', 'id');
    }
    public function pay(): HasOne
    {
        return $this->hasOne(Pay::class);
    }
    public function getStatusLabelAttribute()
    {
        $statusLabels = [
            "Cancelada",
            "Pago pendiente",
            "Pendiente de aprob.",
            "Exitoso"
        ];
        return  $statusLabels[$this->status];
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
        return $months[$this->month];
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
    public function getStatusIconAttribute()
    {
        $status = [
            "eva-close-outline",
            "eva-alert-circle-outline",
            "eva-alert-circle-outline",
            "eva-checkmark-outline"
        ];
        return  $status[$this->status];
    }
}
