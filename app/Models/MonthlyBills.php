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
        'total_maintenance_budget',
        'water_price_per_m3',
        'total_water_bill_amount',
        'total_water_consumption_m3',
        'is_published',
        'generated_at',
    ];
}
