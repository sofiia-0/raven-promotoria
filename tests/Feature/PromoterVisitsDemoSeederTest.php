<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityReschedule;
use App\Models\AuditLog;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Database\Seeders\PromoterVisitsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoterVisitsDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_builds_consistent_fictitious_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));
        // Simula un fichero importado previamente: el seeder no debe tocarlo.
        $imported = Pharmacy::factory()->create(['name' => 'Farmacia del fichero']);

        $this->seed(PromoterVisitsDemoSeeder::class);

        $promoters = User::query()->whereIn('email', PromoterVisitsDemoSeeder::EMAILS)->get();
        $this->assertCount(2, $promoters);

        // Septiembre y octubre enviados, noviembre en borrador, por promotora.
        foreach ($promoters as $promoter) {
            $this->assertSame(
                ['2026-09' => 'ENVIADO', '2026-10' => 'ENVIADO', '2026-11' => 'BORRADOR'],
                MonthlySchedule::query()->where('promoter_id', $promoter->id)->orderBy('year')->orderBy('month')->get()
                    ->mapWithKeys(fn ($s) => [sprintf('%04d-%02d', $s->year, $s->month) => $s->status->value])->all()
            );
        }

        $visits = Visit::query()->get();
        $this->assertGreaterThan(50, $visits->count());
        $this->assertTrue($visits->contains(fn ($v) => $v->isUnplanned()));
        $this->assertTrue($visits->contains('status', VisitStatus::NO_REALIZADA));
        $this->assertTrue($visits->contains(fn ($v) => ! $v->hasLocation()));
        $this->assertGreaterThan(0, ActivityReschedule::count());

        // Nada registrado hoy ni en el futuro; nada sobre el fichero importado.
        $this->assertTrue($visits->every(fn ($v) => $v->visited_at->lt(CarbonImmutable::parse('2026-10-15 06:00:00', 'UTC'))));
        $this->assertSame(0, $imported->visits()->count());
        $this->assertTrue($visits->every(fn ($v) => str_starts_with(Pharmacy::find($v->pharmacy_id)->name, 'PRUEBA - ')));

        $this->assertSame($visits->count(), AuditLog::query()->where('action', 'visit.registered')->count());
        $this->assertSame(4, AuditLog::query()->where('action', 'schedule.submitted')->count());

        // El reloj de la prueba queda como estaba.
        $this->assertTrue(now()->equalTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC')));

        // Re-ejecutar no duplica.
        $this->seed(PromoterVisitsDemoSeeder::class);
        $this->assertSame($visits->count(), Visit::count());
        $this->assertSame(ScheduleStatus::BORRADOR, MonthlySchedule::query()->where('month', 11)->first()->status);
    }
}
