<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AirbnbRent extends Model
{
    //

    protected $table = 'airbnb_rents';
    protected $fillable = ['departament_id', 'assing_to', 'name_to',
    'created_by', 'quantity',	'init_day',	'end_date',	'status', 'created_at', 'updated_at'];
}
