<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditBalance extends Model
{
    const MONTH_FLEXIBLE = 0;

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

    public function isFlexible(): bool
    {
        return (int) $this->applicable_month === self::MONTH_FLEXIBLE;
    }

    public function isEligibleFor(int $month, int $year, bool $isOpenPeriod = false): bool
    {
        if ((float) $this->balance <= 0) {
            return false;
        }

        if ($this->isFlexible()) {
            // Flexible: solo en mes abierto (actual, o anterior si el actual aún no existe)
            return $isOpenPeriod;
        }

        return (int) $this->applicable_month === $month
            && ($this->applicable_year === null || (int) $this->applicable_year === $year);
    }
}
