<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    //
    protected $table = 'currencies';

    protected $fillable = ['name', 'status', 'symbol', 'factor'];

    public function financialAccounts()
    {
        return $this->hasMany(FinancialAccount::class);
    }
}
