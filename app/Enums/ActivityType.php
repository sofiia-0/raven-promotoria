<?php

namespace App\Enums;

enum ActivityType: string
{
    case VISITA = 'VISITA';
    case REUNION = 'REUNION';
    case FERIADO = 'FERIADO';
    case OTRA = 'OTRA';

    public function label(): string
    {
        return match ($this) {
            self::VISITA => 'Visita',
            self::REUNION => 'Reunión',
            self::FERIADO => 'Feriado',
            self::OTRA => 'Otra',
        };
    }

    /** Solo las visitas se vinculan a un establecimiento (RF-PLAN). */
    public function requiresPharmacy(): bool
    {
        return $this === self::VISITA;
    }
}
