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

    public $appends = ['status_label', 'status_color'];

    public function getStatusLabelAttribute()
    {
        return (int) $this->status === 2 ? 'Llegó' : 'Pendiente';
    }

    public function getStatusColorAttribute()
    {
        return (int) $this->status === 2 ? 'primary' : 'warning';
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
