<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterReading extends Model
{
    //
    protected $table = 'water_readings';

    protected $fillable = ['departament_id', 'month', 'year', 'previous_reading', 'current_reading', 'm3_price', 'photo', 'amount', 'is_initial', 'is_common'];

    public $appends = ['month_label', 'consumption'];

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

    public function getConsumptionAttribute()
    {
        return round((float) $this->current_reading - (float) $this->previous_reading, 3);
    }

    public function departament()
    {
        return $this->belongsTo(Departament::class, 'departament_id', 'id');
    }

    public function scopeCommon($query)
    {
        return $query->where('is_common', true);
    }

    public function scopeForDepartment($query)
    {
        return $query->where('is_common', false);
    }
}
