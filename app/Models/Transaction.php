<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = ['financial_account_id', 'transaction_category_id', 'pay_id', 'amount', 'date', 'reference', 'description', 'status', 'type'];

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function transactionCategory(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    public function pay(): BelongsTo
    {
        return $this->belongsTo(Pay::class);
    }
}
