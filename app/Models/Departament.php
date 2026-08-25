<?php

namespace App\Models;

use Database\Factories\Api\DepartamentControllerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Departament extends Model
{
    /** @use HasFactory<DepartamentControllerFactory> */
    use HasFactory;

    const TYPE_DEPARTAMENTO = 1;

    const TYPE_ESTACIONAMIENTO = 2;

    const TYPE_DEPOSITO = 3;

    public $appends = ['inter_number', 'pending_amount_quota', 'type_label'];

    protected $fillable = [
        'number',
        'type',
        'tenant_pays_quota',
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
        return intval(substr($this->number, 4));
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function peoples()
    {
        return $this->hasMany(PeoplesXDepartaments::class, 'departament_id', 'id');
    }

    public function activeTenantPivot()
    {
        return $this->peoples()
            ->where('type', Rol::INQUILINO)
            ->whereHas('user', fn ($q) => $q->where('status', '!=', 3)->whereNull('deleted_at'))
            ->first();
    }

    public function getActiveTenantPivotAttribute()
    {
        return $this->activeTenantPivot()?->load('user');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function visits()
    {
        return $this->hasMany(Visit::class, 'departament_id', 'id');
    }

    public function quotas()
    {
        return $this->hasMany(Quota::class, 'departament_id');
    }

    public function pendingQuotas()
    {
        return $this->hasMany(Quota::class, 'departament_id')->where('status', '=', 1);
    }

    public function dueQuotas()
    {
        return $this->hasMany(Quota::class, 'departament_id')->where('status', '=', 4);
    }

    public function getTypeLabelAttribute()
    {
        $types = [
            '',
            'Departamento',
            'Estacionamiento',
            'Deposito',
        ];

        return $types[$this->type ?? 1];
    }

    protected function pendingAmountQuota(): Attribute
    {
        return Attribute::make(
            get: fn () => Quota::where('departament_id', $this->id)
                ->where('status', '!=', 3)
                ->value('amount')
        );
    }

    public function waterReadings()
    {
        return $this->hasMany(WaterReading::class, 'departament_id');
    }
}
