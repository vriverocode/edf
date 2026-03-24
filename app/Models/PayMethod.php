<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayMethod extends Model
{
    //
    protected $fillable = ['name', 'data','status'];
    public $appends  =   ["status_label", "status_color"];
    public function getStatusLabelAttribute()
    {
        $statusLabels = [
            "Inhabilitado",
            "Habilitado",
        ];
        return  $statusLabels[$this->status];
    }
    public function getStatusColorAttribute()
    {
        $statusLabels = [
            "bg-negative",
            "bg-positive",
        ];
        return  $statusLabels[$this->status];
    }
}
