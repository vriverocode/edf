<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notice extends Model
{
    //
    protected $fillable = [
        'title',
        'description',
        'data_contact',
        'group',
        'category',
        'img',
        'type',
        'views',
        'status',
        'user_id',
    ];

    public $appends = ['group_label', 'category_label', 'status_label', 'status_color'];

    public const GROUPS = [
        0 => 'Información',
        1 => '🛍 Emprendimientos',
        2 => '🛠️ Servicios',
        3 => '🛒 Compra, venta y alquiler',
        4 => '💼 Oportunidades',
        5 => '🤝 Comunidad',
    ];

    public const CATEGORIES = [
        0 => [
            0 => 'Información junta de condominio',
        ],
        1 => [
            0 => '🍰 Alimentos y postres',
            1 => '👕 Moda y accesorios',
            2 => '💄 Belleza y bienestar',
            3 => '🏠 Hogar y decoración',
            4 => '🎁 Regalos y personalizados',
            5 => '🧵 Artesanías y manualidades',
            6 => '📦 Otros emprendimientos',
        ],
        2 => [
            0 => '👨‍🔧 Reparaciones y mantenimiento',
            1 => '🧹 Limpieza',
            2 => '👩‍🏫 Clases y tutorías',
            3 => '🐶 Mascotas',
            4 => '💻 Tecnología',
            5 => '🚗 Transporte',
            6 => '📋 Otros servicios',
        ],
        3 => [
            0 => '🛍️ Venta de artículos',
            1 => '🔎 Busco comprar',
            2 => '🏷️ Alquiler',
            3 => '🚗 Vehículos y accesorios',
            4 => '🏠 Inmuebles',
        ],
        4 => [
            0 => '👔 Empleo',
            1 => '🎉 Eventos',
            2 => '🎁 Promociones y descuentos',
            3 => '📢 Otros',
        ],
        5 => [
            0 => 'Objetos perdidos',
            1 => 'Donaciones',
            2 => 'Intercambios',
            3 => 'Recomendaciones',
        ],
    ];

    public const STATUS_LABELS = [
        0 => 'Rechazada',
        1 => 'Pendiente de aprb.',
        2 => 'Publicada',
    ];

    public const STATUS_COLORS = [
        0 => 'negative',
        1 => 'warning',
        2 => 'positive',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getGroupLabelAttribute()
    {
        return self::GROUPS[$this->group] ?? '';
    }

    public function getCategoryLabelAttribute()
    {
        return self::CATEGORIES[$this->group][$this->category] ?? '';
    }

    public function getStatusLabelAttribute()
    {
        return self::STATUS_LABELS[$this->status] ?? '';
    }

    public function getStatusColorAttribute()
    {
        return self::STATUS_COLORS[$this->status] ?? '';
    }
}
