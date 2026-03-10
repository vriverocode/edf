<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Departament extends Model
{
    /** @use HasFactory<\Database\Factories\Api\DepartamentControllerFactory> */
    use HasFactory;

    public $appends  =   ["inter_number"];

    protected $fillable = [
        'number',
        'address',
        'block',
        'area',
        'description',
        'code_pay',
        'floor',
        'user_id'

    ];
    public function getInterNumberAttribute()
    {
        return substr($this->number, -3);
    }
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function peoples()
    {
        return $this->hasMany(PeoplesXDepartaments::class, 'departament_id', 'id');
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    public function visits()
    {
        return $this->hasMany(Visit::class, 'departament_id', 'id');
    }
}
