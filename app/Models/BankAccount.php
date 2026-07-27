<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'entity',
        'account_number',
        'cci',
        'holder_name',
        'yape_phone',
        'yape_name',
        'is_default',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
