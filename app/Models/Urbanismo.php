<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Urbanismo extends Model
{
    protected $fillable = [
        'nombre',
        'codigo_invitacion',
        'direccion',
        'ciudad',
        'logo_url',
        'moneda_principal',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Urbanismo $urbanismo) {
            if (blank($urbanismo->codigo_invitacion)) {
                $urbanismo->codigo_invitacion = self::generateInvitationCode();
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function findByInvitationCode(string $code): ?self
    {
        return static::query()
            ->where('codigo_invitacion', Str::upper(trim($code)))
            ->first();
    }

    public static function generateInvitationCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (static::query()->where('codigo_invitacion', $code)->exists());

        return $code;
    }
}
