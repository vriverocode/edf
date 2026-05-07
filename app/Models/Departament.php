<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\Quota; 

// use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Departament extends Model
{
    /** @use HasFactory<\Database\Factories\Api\DepartamentControllerFactory> */
    use HasFactory;
    const TYPE_DEPARTAMENTO = 1;
    const TYPE_ESTACIONAMIENTO = 2;
    const TYPE_DEPOSITO = 3;
    public $appends  =   ["inter_number", "pending_amount_quota"];

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
    protected function pendingAmountQuota(): Attribute
    {
        return Attribute::make(
            get: fn () => Quota::where('departament_id', $this->id)
                            ->where('status', '!=', 3)
                            ->value('amount')
        );
    }
}
