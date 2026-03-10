<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeoplesXDepartaments extends Model
{
    protected $table = 'peoples_x_departments';

    protected $fillable = ['user_id', 'departament_id', 'type', 'created_by'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function departament()
    {
        return $this->belongsTo(Departament::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
