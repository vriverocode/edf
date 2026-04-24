<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'phone',
        'password',
        'status',
        'device_token',
        'rol_id',
        'active_time',
        'end_time',
        'parentesco',
    ];

    public $appends  =   ['status_label'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getStatusLabelAttribute()
    {
        $status = [
            '---',
            'Solvente',
            'Moroso',
        ];

        return $status[$this->status];
    }
    public function apartaments()
    {
        return $this->hasMany(Departament::class);
    }
    public function departments()
    {
        return $this->hasMany(PeoplesXDepartaments::class)->withPivot('created_by') // Declarar el campo extra
                ->withTimestamps();
    }
    public function departmentsOwner()
    {
        return $this->hasMany(PeoplesXDepartaments::class)->where('type', Rol::PROPIETARIO);
    }
    public function departmentsInquilino()
    {
        return $this->hasMany(PeoplesXDepartaments::class)->where('type', Rol::INQUILINO);
    }
    public function notices()
    {
        return $this->hasMany(Notice::class)->where('type', 1);
    }
    public function announces()
    {
        return $this->hasMany(Notice::class)->where('type', 2);
    }
    public function rol()
    {
        return $this->belongsTo(Rol::class);
    }
    public function routeNotificationForFcm()
    {
        return $this->device_token;
    }
}
