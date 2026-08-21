<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    //
    protected $table = 'service_categories';

    protected $fillable = ['name', 'status'];

    public function providers()
    {
        return $this->hasMany(Provider::class, 'service_category_id', 'id');
    }
}
