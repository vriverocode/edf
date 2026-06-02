<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
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
            "Inactivo",
            "Activo"
        ];

        return $statusLabels[$this->status] ?? '—';
    }
    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }
    

}
