<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaterReading extends Model
{
    //
    protected $table = 'water_readings';
    protected $fillable = ['departament_id', 'month', 'year', 'previous_reading', 'current_reading', 'm3_price', 'photo'];

    public function departament()
    {
        return $this->belongsTo(Departament::class, 'departament_id', 'id');
    }
}
