<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PlanningCalendar
{
    /** Cuadrícula civil de lunes a domingo; no cambia las semanas persistidas de Raven. */
    public static function days(CarbonImmutable $month): array
    {
        $start = $month->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonInterface::SUNDAY)->startOfDay();
        $days = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days[] = ['date' => $day->toDateString(), 'number' => $day->day, 'in_month' => $day->format('Y-m') === $month->format('Y-m')];
        }

        return $days;
    }
}
