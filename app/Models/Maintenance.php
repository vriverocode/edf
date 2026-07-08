<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    protected $table = 'maintenances';

    protected $fillable = [
        'title',
        'description',
        'comun_area_id',
        'date',
        'time_from',
        'time_to',
        'status',
        'photo',
    ];

    public $appends = ['status_label'];

    /**
     * Obtiene el área común asociada al mantenimiento (si aplica)
     */
    public function comunArea(): BelongsTo
    {
        return $this->belongsTo(ComunArea::class, 'comun_area_id', 'id');
    }

    public function getStatusLabelAttribute()
    {
        $status = [
            'Cancelada',
            'Pendiente',
            'Terminado',
            'Pospuestas',
        ];

        return $status[$this->status] ?? '—';
    }
}
