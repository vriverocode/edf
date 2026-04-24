<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'departament_id',
        'fullname',
        'dni',
        'type',
        'description',
        'date',
        'hour', 
        'status',
        'airbnb_rent_id'
    ];
    public $appends  =   ["status_label", "status_color", "type_label"];
    public function departament()
    {
        return $this->belongsTo(Departament::class);
    }
    public function airbnb()
    {
        return $this->belongsTo(AirbnbRent::class, "airbnb_rent_id");
    }
    public function getStatusLabelAttribute()
    {
        $statusLabels = [
            "Cancelada",
            "Pendiente de llegada",
            "Llegada confirmada.",
        ];
        return  $statusLabels[$this->status];
    }
    public function getStatusColorAttribute()
    {
        $color = [
            "negative",
            "warning",
            "primary",
        ];
        return  $color[$this->status];
    }
    public function getTypeLabelAttribute()
    {
        $labels = [
            1 => 'Personal',
            2 => 'Entrega',
            3 => 'AirBnb',
            4 => 'Servicio',
            5 => 'Otro',
        ];
        return $labels[$this->type] ?? 'Visita';
    }
}
