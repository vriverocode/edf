<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunAreaSchedule extends Model
{
    protected $fillable = ['comun_area_id', 'day', 'time_from', 'time_to'];

    public function comunArea()
    {
        return $this->belongsTo(ComunArea::class);
    }
}