<?php

declare(strict_types=1);

namespace App\Enums;

enum RegistrationStatus: string
{
    case Pendiente = 'pendiente';

    case Activo = 'activo';

    case Cuarentena = 'cuarentena';

    case Suspendido = 'suspendido';
}
