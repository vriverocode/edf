<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterReading extends Model
{
    //
    protected $table = 'water_readings';

    protected $fillable = ['departament_id', 'month', 'year', 'previous_reading', 'current_reading', 'm3_price', 'photo', 'amount', 'is_initial'];

    public $appends = ['month_label'];

    public function getMonthLabelAttribute()
    {
        $monthOptions = [
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
            'Diciembre',
        ];

        return $monthOptions[$this->month];
    }

    public function departament()
    {
        return $this->belongsTo(Departament::class, 'departament_id', 'id');
    }
}
