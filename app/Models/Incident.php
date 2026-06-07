<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    protected $fillable = [
        'title',
        'description',
        'date',
        'hour',
        'location',
        'images',
        'videos',
        'files',
        'status',
        'type',
        'user_id',
    ];
    protected $appends = ['status_label', 'type_label'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function getStatusLabelAttribute()
    {
        $status = [
            '',
            'Pendiente',
            'Atendido',
            'Pendiente de aprobación',
            'Resuelto',
        ];

        return $status[$this->status];
    }

    public function getTypeLabelAttribute()
    {
        $typeLabels = [
            '',
            'Consulta por duda',
            'Reclamos',
            'Averias de infraestructura',
            'Averias en equipos(Ascensores, caminadoras, butacas)',
            'Incumplimiento de reglas y normativas',
            'Fallas generales',
            'Maltrato por parte de propietario',
        ];
        return $typeLabels[$this->type];
    }
}
