<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualBudget extends Model
{
    protected $fillable = ['year', 'name', 'total_monthly_budget', 'status'];

    const STATUS_BORRADOR = 1;

    const STATUS_ACTIVO = 2;

    const STATUS_CERRADO = 3;

    public function templates(): HasMany
    {
        return $this->hasMany(Expense::class, 'annual_budget_id')
            ->where('is_template', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVO);
    }
}
