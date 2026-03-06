<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'title',
        'description',
        'punish',
        'type',
        'severity',
        'active',
        'suggest_amount',
    ];
    // Rule.php
    public function multa()
    {
        return $this->hasMany(Multa::class);
    }
}
