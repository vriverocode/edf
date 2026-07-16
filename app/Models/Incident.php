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
            '🔧 Avería de infraestructura',
            '⚙️ Avería de equipos (gimnasio, ascensores, etc.)',
            '⚠️ Incumplimiento de normas',
            '📢 Reclamo',
            '❓ Consulta',
            '🏢 Falla general',
            '👤 Conducta inadecuada de un residente',
            '🛡️ Incidente con personal del edificio',
            '📝 Otro',
        ];

        return $typeLabels[$this->type];
    }
}
