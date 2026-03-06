<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Multa extends Model
{
    //
    protected $fillable = [
        'rule_id',
        'departament_id',
        'type',
        'description',
        'incident_date',
        'amount',
        'pay_id',
        'status',
    ];
}
