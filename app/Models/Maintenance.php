<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'series_id',
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

    /**
     * Un mantenimiento de varios dias se guarda como una fila por dia, todas
     * compartiendo el mismo series_id. Las filas antiguas (series_id null) se
     * consideran series de un solo dia.
     *
     * El area se recibe como parametro y no por $this porque el scope se invoca
     * de forma estatica (Maintenance::inSeries($maintenance)); si se usara $this
     * se resolveria contra una instancia vacia y el where sobre comun_area_id
     * terminaria siendo un whereNull.
     */
    public function scopeInSeries(Builder $query, ?self $maintenance = null): Builder
    {
        $maintenance ??= $this;

        return $query->where('comun_area_id', $maintenance->comun_area_id)
            ->where('series_id', $maintenance->series_id);
    }

    public function seriesTotal(): int
    {
        if ($this->series_id === null) {
            return 1;
        }

        return static::where('comun_area_id', $this->comun_area_id)
            ->where('series_id', $this->series_id)
            ->count();
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
