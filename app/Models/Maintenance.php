<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Maintenance extends Model
{
    public const STATUS_CANCELLED = 0;

    public const STATUS_PENDING = 1;

    public const STATUS_COMPLETED = 2;

    public const STATUS_PENDING_MATERIAL = 3;

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
        'evidence_photo',
        'completion_description',
        'completed_at',
        'completed_by',
    ];

    public $appends = ['status_label'];

    /**
     * Obtiene el área común asociada al mantenimiento (si aplica)
     */
    public function comunArea(): BelongsTo
    {
        return $this->belongsTo(ComunArea::class, 'comun_area_id', 'id');
    }

    /**
     * Usuario que marcó el mantenimiento como completado
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by', 'id');
    }

    public function getStatusLabelAttribute()
    {
        $status = [
            'Cancelado',
            'Pendiente',
            'Completado',
            'Pendiente de material',
        ];

        return $status[$this->status] ?? '—';
    }
}
