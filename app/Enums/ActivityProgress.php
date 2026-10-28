<?php

namespace App\Enums;

/** Estado derivado de una visita planificada (no se guarda: se calcula desde `visits`). */
enum ActivityProgress: string
{
    case PENDIENTE = 'PENDIENTE';
    case REALIZADA = 'REALIZADA';
    case NO_REALIZADA = 'NO_REALIZADA';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::REALIZADA => 'Realizada',
            self::NO_REALIZADA => 'No realizada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PENDIENTE => 'raven-badge-neutral',
            self::REALIZADA => 'raven-badge-success',
            self::NO_REALIZADA => 'raven-badge-danger',
        };
    }
}
