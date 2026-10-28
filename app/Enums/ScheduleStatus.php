<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case BORRADOR = 'BORRADOR';
    case ENVIADO = 'ENVIADO';

    public function label(): string
    {
        return match ($this) {
            self::BORRADOR => 'Borrador',
            self::ENVIADO => 'Enviado',
        };
    }
}
