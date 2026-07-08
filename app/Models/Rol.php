<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    public const ADMIN = 1;

    public const PROPIETARIO = 2;

    public const INQUILINO = 3;

    public const FAMILIAR = 4;

    public const AIRBNB = 5;

    public const TRABAJADOR = 6;

    public const PARCIAL = 7;

    /** Nombres de rol para uso en middleware (deben coincidir con roles.name en BD) */
    public const ADMIN_NAME = 'Admin';

    public const PROPIETARIO_NAME = 'Propietario';

    public const INQUILINO_NAME = 'Inquilino';

    public const FAMILIAR_NAME = 'Familiar';

    public const AIRBNB_NAME = 'Airbnb';

    public const TRABAJADOR_NAME = 'Trabajador';

    public const PARCIAL_NAME = 'Propietario Parcial';

    protected $table = 'roles';
}
