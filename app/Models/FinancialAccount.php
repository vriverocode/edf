<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FinancialAccount extends Model
{
    //
    protected $table = 'financial_accounts';
    protected $fillable = ['name', 'currency_id', 'current_balance', 'initial_balance', 'status', 'type'];
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'id');
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
