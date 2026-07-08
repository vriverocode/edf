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

    public function rule()
    {
        return $this->belongsTo(Rule::class);
    }

    public function user()
    {
        return $this->belongsTo(Departament::class, 'departament_id', 'id');
    }
}
