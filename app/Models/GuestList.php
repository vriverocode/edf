<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestList extends Model
{
    protected $fillable = [
        'name',
        'dni',
        'age',
        'booking_id',
        'status',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
