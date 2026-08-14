<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirbnbRent extends Model
{
    //

    protected $table = 'airbnb_rents';

    protected $fillable = ['departament_id', 'assing_to', 'name_to',
        'created_by', 'quantity',	'init_day',	'end_date',	'status', 'created_at', 'updated_at'];

    public $appends = ['status_label', 'status_color'];

    public function getStatusLabelAttribute()
    {
        $statusLabels = [
            'Cancelada',
            'Pendiente',
            'Confirmada',
            'Terminada',
            'Eliminada'
        ];

        return $statusLabels[$this->status];
    }

    public function getStatusColorAttribute()
    {
        $color = [
            'negative',
            'warning',
            'primary',
        ];

        return $color[$this->status];
    }

    public function guest()
    {
        return $this->hasMany(Visit::class, 'airbnb_rent_id');
    }

    public function departament()
    {
        return $this->belongsTo(Departament::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'assing_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
