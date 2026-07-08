<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeoplesXDepartaments extends Model
{
    protected $table = 'peoples_x_departments';

    protected $fillable = ['user_id', 'departament_id', 'type', 'created_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function departament(): BelongsTo
    {
        return $this->belongsTo(Departament::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quotas(): HasMany
    {
        return $this->hasMany(Quota::class, 'peoples_x_departments_id');
    }
}
