<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditBalance extends Model
{
    protected $fillable = [
        'departament_id',
        'balance',
        'applicable_month',
        'applicable_year',
    ];

    protected $casts = [
        'balance' => 'float',
        'applicable_month' => 'integer',
        'applicable_year' => 'integer',
    ];

    public function departament(): BelongsTo
    {
        return $this->belongsTo(Departament::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class, 'departament_id', 'departament_id');
    }
}
