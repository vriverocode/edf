<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'departament_id',
        'type',
        'amount',
        'balance_after',
        'pay_id',
        'quota_id',
        'description',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_after' => 'float',
        'created_at' => 'datetime',
    ];

    const TYPE_CREATED = 'created';

    const TYPE_APPLIED = 'applied';

    const TYPE_EXPIRED = 'expired';

    public function departament(): BelongsTo
    {
        return $this->belongsTo(Departament::class);
    }

    public function pay(): BelongsTo
    {
        return $this->belongsTo(Pay::class);
    }

    public function quota(): BelongsTo
    {
        return $this->belongsTo(Quota::class);
    }
}
