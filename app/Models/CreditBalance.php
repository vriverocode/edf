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
    ];

    protected $casts = [
        'balance' => 'float',
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
