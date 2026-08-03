<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use SoftDeletes;

    protected $table = 'providers';
    protected $fillable = [
        'name',
        'tax_id',
        'contact_name',
        'phone',
        'email',
        'service_category_id',
        'bank_details',
        'status',
    ];


    public $appends = ['status_label'];

    public function getStatusLabelAttribute()
    {
        $statusLabels = [
            'Inactivo',
            'Activo',
        ];

        return $statusLabels[$this->status] ?? '—';
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }
}
