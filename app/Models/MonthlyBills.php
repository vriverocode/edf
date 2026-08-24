<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyBills extends Model
{
    //
    protected $table = 'monthly_bills';

    protected $fillable = [
        'month',
        'year',
        'monthly_budget',
        'total_maintenance_budget',
        'water_price_per_m3',
        'total_water_bill_amount',
        'total_water_consumption_m3',
        'is_published',
        'generated_at',
    ];

    protected $casts = [
        'monthly_budget' => 'decimal:2',
        'total_maintenance_budget' => 'decimal:2',
        'water_price_per_m3' => 'decimal:4',
        'total_water_bill_amount' => 'decimal:2',
        'total_water_consumption_m3' => 'decimal:2',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'monthly_bill_id');
    }
}
