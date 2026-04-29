<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use phpDocumentor\Reflection\DocBlock\Tags\Return_;

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
        'user_id',
        'participation_percentage',
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
    public function quotas() {
        return $this->hasMany(Quota::class, 'departament_id');
    }
    public function pendingQuotas() {
        return $this->hasMany(Quota::class, 'departament_id')->where('status','!=', 3);
    }
}
