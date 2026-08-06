<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Refund extends Model
{
    use SoftDeletes;

    protected $table = 'table_refunds';

    protected $fillable = [
        'booking_id',
        'pay_id',
        'amount',
        'reason',
        'type',
        'kind',
        'vaucher',
        'bank_account_id',
        'bank_account_snapshot',
        'status',
    ];

    protected $casts = [
        'bank_account_snapshot' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function pay(): BelongsTo
    {
        return $this->belongsTo(Pay::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
