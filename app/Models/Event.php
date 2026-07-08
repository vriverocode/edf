<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    //
    protected $fillable = [
        'title',
        'description',
        'date',
        'time_from',
        'time_to',
        'location',
        'assits',
        'not_assits',
        'booking_id',
    ];

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class, 'id', 'booking_id');
    }
}
