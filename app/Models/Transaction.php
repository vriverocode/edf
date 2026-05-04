<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    //

    protected $fillable = ['financial_account_id', 'transaction_category_id', 'pay_id', 'amount', 'date', 'reference', 'description', 'status', 'type'];
    public function financialAccount()
    {
        return $this->belongsTo(FinancialAccount::class);
    }
    public function transactionCategory()
    {
        return $this->belongsTo(TransactionCategory::class);
    }
}
