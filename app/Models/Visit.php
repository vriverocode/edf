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

    public function departament()
    {
        return $this->belongsTo(Departament::class);
    }
}
