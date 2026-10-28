<?php

namespace App\Enums;

enum VisitStatus: string
{
    case REALIZADA = 'REALIZADA';
    case NO_REALIZADA = 'NO_REALIZADA';

    public function label(): string
    {
        return match ($this) {
            self::REALIZADA => 'Realizada',
            self::NO_REALIZADA => 'No realizada',
        };
    }
}
