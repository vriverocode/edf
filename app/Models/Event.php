<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Event extends Model
{
    public const STATUS_CANCELLED = 0;

    public const STATUS_UPCOMING = 1;

    public const STATUS_IN_PROGRESS = 2;

    public const STATUS_COMPLETED = 3;

    //
    protected $fillable = [
        'title',
        'description',
        'date',
        'time_from',
        'time_to',
        'status',
        'location',
        'assits',
        'not_assits',
        'booking_id',
    ];

    public $appends = ['status_label', 'status_color'];

    protected function casts(): array
    {
        return [
            'assits' => 'array',
            'not_assits' => 'array',
        ];
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ((int) $this->status) {
                self::STATUS_CANCELLED => 'Cancelada',
                self::STATUS_UPCOMING => 'Próximamente',
                self::STATUS_IN_PROGRESS => 'En curso',
                self::STATUS_COMPLETED => 'Completa',
                default => 'Desconocido',
            }
        );
    }

    protected function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ((int) $this->status) {
                self::STATUS_CANCELLED => 'negative',
                self::STATUS_UPCOMING => 'info',
                self::STATUS_IN_PROGRESS => 'warning',
                self::STATUS_COMPLETED => 'positive',
                default => 'grey',
            }
        );
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class, 'id', 'booking_id');
    }
}
