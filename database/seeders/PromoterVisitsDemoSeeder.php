<?php

namespace Database\Seeders;

use App\Enums\ActivityProgress;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Models\VisitReason;
use App\Services\SchedulePlanner;
use App\Services\VisitRecorder;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Datos de PRUEBA para planificación y visitas (solo local/testing).
 *
 * Crea dos promotoras y farmacias ficticias, propias de este seeder (no toca el fichero importado),
 * y simula el mes anterior y el actual día a día con los servicios reales:
 * cronogramas enviados, visitas realizadas/no realizadas con checklist, reprogramaciones,
 * visitas fuera de plan y bitácora. Las visitas de hoy quedan pendientes.
 *
 * php artisan db:seed --class=PromoterVisitsDemoSeeder
 */
class PromoterVisitsDemoSeeder extends Seeder
{
    public const EMAILS = ['promotora.prueba1@raven.test', 'promotora.prueba2@raven.test'];

    private const PHARMACIES = [
        ['San Rafael', 'Centro', 'Managua', 'Managua', 'AAA'],
        ['La Esperanza', 'Altamira', 'Managua', 'Managua', 'AA'],
        ['El Buen Pastor', 'Bello Horizonte', 'Managua', 'Managua', 'A'],
        ['Santa Lucía', 'Monumental', 'Masaya', 'Masaya', 'AA'],
        ['Divino Niño', 'Centro', 'Granada', 'Granada', 'AAA'],
        ['Los Ángeles', 'Las Brisas', 'Ciudad Sandino', 'Managua', 'A'],
        ['San Judas', 'San Judas', 'Managua', 'Managua', 'AA'],
        ['Vida Sana', 'Sutiava', 'León', 'León', 'AAA'],
        ['El Carmen', 'El Carmen', 'Managua', 'Managua', 'A'],
        ['Monte Tabor', 'Centro', 'Jinotepe', 'Carazo', 'AA'],
        ['Nueva Salud', 'Linda Vista', 'Managua', 'Managua', 'AAA'],
        ['Santa Ana', 'Monimbó', 'Masaya', 'Masaya', 'A'],
    ];

    /** Coordenadas aproximadas por ciudad para simular la geolocalización. */
    private const COORDS = [
        'Managua' => [12.1364, -86.2514], 'Masaya' => [11.9744, -86.0942], 'Granada' => [11.9299, -85.9560],
        'Ciudad Sandino' => [12.1600, -86.3440], 'León' => [12.4379, -86.8780], 'Jinotepe' => [11.8463, -86.1990],
    ];

    private SchedulePlanner $planner;

    private VisitRecorder $recorder;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Seeder omitido: solo puede ejecutarse en local/testing.');

            return;
        }

        if (User::query()->whereIn('email', self::EMAILS)->exists()) {
            $this->command?->warn('Los datos de prueba de visitas ya existen. Para regenerarlos: migrate:fresh y volver a sembrar.');

            return;
        }

        $this->call(VisitCatalogSeeder::class);
        $this->planner = app(SchedulePlanner::class);
        $this->recorder = app(VisitRecorder::class);
        mt_srand(2026); // Resultados reproducibles.

        $password = env('DEMO_SEED_PASSWORD') ?: Str::password(16, symbols: false);
        $realToday = SchedulePlanner::today();
        $previousNow = Carbon::getTestNow();

        try {
            foreach (self::EMAILS as $index => $email) {
                $promoter = User::query()->create([
                    'name' => 'Promotora Prueba '.($index + 1),
                    'email' => $email,
                    'password' => $password,
                    'role' => UserRole::PROMOTER,
                    'is_active' => true,
                ]);

                $pharmacies = $this->pharmaciesFor($promoter, array_slice(self::PHARMACIES, $index * 6, 6));
                $this->simulateMonth($promoter, $pharmacies, $realToday->subMonthNoOverflow()->startOfMonth(), $realToday);
                $this->simulateMonth($promoter, $pharmacies, $realToday->startOfMonth(), $realToday);
                $this->draftNextMonth($promoter, $pharmacies, $realToday);
            }
        } finally {
            Carbon::setTestNow($previousNow);
            CarbonImmutable::setTestNow($previousNow);
        }

        $this->command?->info('Datos de prueba creados: '.implode(', ', self::EMAILS));
        if (! env('DEMO_SEED_PASSWORD')) {
            $this->command?->warn('Contraseña temporal (se muestra una sola vez): '.$password);
        }
    }

    /** @return list<Pharmacy> */
    private function pharmaciesFor(User $promoter, array $rows): array
    {
        return array_map(function (array $row) use ($promoter): Pharmacy {
            [$name, $neighborhood, $city, $department, $category] = $row;
            $pharmacy = Pharmacy::query()->create([
                'name' => 'PRUEBA - Farmacia '.$name,
                'client_type' => 'FARMACIA INDEPENDIENTE',
                'category' => $category,
                'neighborhood' => $neighborhood,
                'address' => 'Dirección ficticia de prueba',
                'city' => $city,
                'department' => $department,
                'frequency' => 2,
                'is_active' => true,
            ]);
            $pharmacy->assignments()->create([
                'promoter_id' => $promoter->id,
                'assigned_at' => CarbonImmutable::now()->subMonths(3),
            ]);

            return $pharmacy;
        }, $rows);
    }

    /** @param list<Pharmacy> $pharmacies */
    private function simulateMonth(User $promoter, array $pharmacies, CarbonImmutable $month, CarbonImmutable $realToday): void
    {
        // Se planifica unos días antes de que empiece el mes.
        $this->travel($month->subDays(3), 16);
        $schedule = $this->planner->create($promoter, $month->year, $month->month);

        $rotation = 0;
        for ($day = $month; $day->isSameMonth($month); $day = $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }
            // Dos farmacias distintas por día hábil, rotando el fichero.
            foreach ([0, 1] as $slot) {
                $pharmacy = $pharmacies[$rotation++ % count($pharmacies)];
                $this->planner->addActivity($promoter, $schedule->id, [
                    'activity_type' => 'VISITA',
                    'scheduled_date' => $day->toDateString(),
                    'pharmacy_id' => $pharmacy->id,
                    'notes' => $slot === 0 && mt_rand(1, 4) === 1 ? 'Revisar exhibición de la línea nueva' : null,
                ]);
            }
            if ($day->dayOfWeekIso === 1 && $day->day <= 7) {
                $this->planner->addActivity($promoter, $schedule->id, [
                    'activity_type' => 'REUNION',
                    'scheduled_date' => $day->toDateString(),
                    'notes' => 'Reunión mensual con coordinación',
                ]);
            }
        }
        $this->planner->submit($promoter, $schedule->id);

        // Día a día hasta AYER: hoy queda pendiente para probar el registro en vivo.
        $last = $month->endOfMonth()->startOfDay()->min($realToday->subDay());
        for ($day = $month; $day->lte($last); $day = $day->addDay()) {
            $this->simulateDay($promoter, $schedule, $pharmacies, $day);
        }
    }

    /** @param list<Pharmacy> $pharmacies */
    private function simulateDay(User $promoter, MonthlySchedule $schedule, array $pharmacies, CarbonImmutable $day): void
    {
        $activities = PlannedActivity::query()
            ->whereHas('week', fn ($q) => $q->where('monthly_schedule_id', $schedule->id))
            ->where('activity_type', 'VISITA')
            ->whereDate('scheduled_date', $day->toDateString())
            ->with('visits')
            ->orderBy('id')
            ->get();

        $hour = 9;
        foreach ($activities as $activity) {
            if ($activity->progress() !== ActivityProgress::PENDIENTE) {
                continue;
            }
            $this->travel($day, $hour, mt_rand(0, 50));
            $hour += 2;

            $roll = mt_rand(1, 100);
            if ($roll <= 8) {
                continue; // Sin registro: queda atrasada.
            }

            if ($roll <= 80) {
                $this->register($promoter, $activity->pharmacy_id, $activity->id, 'REALIZADA');

                continue;
            }

            $this->register($promoter, $activity->pharmacy_id, $activity->id, 'NO_REALIZADA');
            // Dos de cada tres no realizadas se reprograman a un día hábil cercano del mismo mes.
            if (mt_rand(1, 3) <= 2) {
                $this->rescheduleSoon($promoter, $activity, $day);
            }
        }

        // Alguna visita fuera de plan, de vez en cuando.
        if (! $day->isWeekend() && mt_rand(1, 5) === 1) {
            $this->travel($day, 17, mt_rand(0, 40));
            $pharmacy = $pharmacies[mt_rand(0, count($pharmacies) - 1)];
            $this->register($promoter, $pharmacy->id, null, mt_rand(1, 6) === 1 ? 'NO_REALIZADA' : 'REALIZADA');
        }
    }

    private function register(User $promoter, int $pharmacyId, ?int $activityId, string $status): void
    {
        $pharmacy = Pharmacy::query()->find($pharmacyId);
        $located = mt_rand(1, 100) <= 80; // A veces la promotora deniega la ubicación.
        [$lat, $lng] = self::COORDS[$pharmacy->city] ?? self::COORDS['Managua'];

        $checklist = [];
        foreach (ChecklistItem::query()->active()->pluck('id') as $itemId) {
            $checklist[$itemId] = mt_rand(1, 100) <= 75;
        }

        $this->recorder->register($promoter, [
            'idempotency_key' => (string) Str::uuid(),
            'status' => $status,
            'planned_activity_id' => $activityId,
            'pharmacy_id' => $activityId ? null : $pharmacyId,
            'visit_reason_id' => $status === 'NO_REALIZADA' ? $this->reasonId() : null,
            'notes' => mt_rand(1, 3) === 1 ? 'Observación de prueba generada automáticamente.' : null,
            'latitude' => $located ? round($lat + mt_rand(-300, 300) / 10000, 7) : null,
            'longitude' => $located ? round($lng + mt_rand(-300, 300) / 10000, 7) : null,
            'location_accuracy' => $located ? mt_rand(5, 60) : null,
            'checklist' => $checklist,
        ]);
    }

    private function rescheduleSoon(User $promoter, PlannedActivity $activity, CarbonImmutable $day): void
    {
        $target = $day->addDays(mt_rand(1, 4));
        while ($target->isWeekend()) {
            $target = $target->addDay();
        }
        if (! $target->isSameMonth($day)) {
            return;
        }

        try {
            $this->planner->reschedule($promoter, $activity->id, $target->toDateString(), $this->reasonId(), 'Reprogramada tras visita no realizada.');
        } catch (ValidationException) {
            // Ese día ya tenía la misma farmacia: se deja como no realizada.
        }
    }

    /** @param list<Pharmacy> $pharmacies */
    private function draftNextMonth(User $promoter, array $pharmacies, CarbonImmutable $realToday): void
    {
        $this->travel($realToday, 8);
        $next = $realToday->addMonthNoOverflow()->startOfMonth();
        $schedule = $this->planner->create($promoter, $next->year, $next->month);

        $day = $next;
        foreach (array_slice($pharmacies, 0, 3) as $pharmacy) {
            while ($day->isWeekend()) {
                $day = $day->addDay();
            }
            $this->planner->addActivity($promoter, $schedule->id, [
                'activity_type' => 'VISITA',
                'scheduled_date' => $day->toDateString(),
                'pharmacy_id' => $pharmacy->id,
            ]);
            $day = $day->addDay();
        }
    }

    private function reasonId(): int
    {
        $ids = VisitReason::query()->active()->pluck('id');

        return $ids[mt_rand(0, $ids->count() - 1)];
    }

    /** Mueve el reloj a la hora indicada de Managua (las fechas se guardan en UTC). */
    private function travel(CarbonImmutable $day, int $hour, int $minute = 0): void
    {
        $moment = CarbonImmutable::create($day->year, $day->month, $day->day, $hour, $minute, 0, SchedulePlanner::TIMEZONE)->utc();
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }
}
