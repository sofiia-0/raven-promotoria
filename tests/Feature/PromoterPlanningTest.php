<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\ScheduleStatus;
use App\Enums\UserRole;
use App\Livewire\Promoter\Planning\Index;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Services\PharmacyAssignmentService;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PromoterPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // 15/10/2026 06:00 en Managua.
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));
    }

    private function promoter(): User
    {
        return User::factory()->create([
            'role' => UserRole::PROMOTER,
            'is_active' => true,
        ]);
    }

    private function pharmacy(User $promoter, string $name = 'Farmacia Central', bool $active = true): Pharmacy
    {
        $pharmacy = Pharmacy::create([
            'name' => $name,
            'city' => 'Managua',
            'department' => 'Managua',
            'is_active' => $active,
        ]);

        PharmacyAssignment::create([
            'pharmacy_id' => $pharmacy->id,
            'promoter_id' => $promoter->id,
            'assigned_at' => now()->subDay(),
        ]);

        return $pharmacy;
    }

    private function october(User $promoter): MonthlySchedule
    {
        return (new SchedulePlanner)->create($promoter, 2026, 10);
    }

    /** RF-PLAN: semanas 1–5, contiguas y dentro del mes. */
    public function test_weeks_are_monday_based_and_never_exceed_five(): void
    {
        $october = SchedulePlanner::weeksOf(2026, 10);
        $this->assertSame(
            [['2026-10-01', '2026-10-04'], ['2026-10-05', '2026-10-11'], ['2026-10-12', '2026-10-18'],
                ['2026-10-19', '2026-10-25'], ['2026-10-26', '2026-10-31']],
            array_map(fn ($w) => [$w['starts_on']->toDateString(), $w['ends_on']->toDateString()], $october)
        );

        // Agosto 2026 empieza sábado y tiene 31 días: el fin de semana inicial se une a la semana 1.
        $august = SchedulePlanner::weeksOf(2026, 8);
        $this->assertCount(5, $august);
        $this->assertSame('2026-08-01', $august[0]['starts_on']->toDateString());
        $this->assertSame('2026-08-09', $august[0]['ends_on']->toDateString());
        $this->assertSame('2026-08-31', $august[4]['starts_on']->toDateString());

        for ($year = 2026; $year <= 2030; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $weeks = SchedulePlanner::weeksOf($year, $month);
                $this->assertLessThanOrEqual(5, count($weeks));
                $this->assertSame(1, $weeks[0]['starts_on']->day);
                $this->assertTrue(end($weeks)['ends_on']->isLastOfMonth());
                foreach (array_slice($weeks, 1) as $i => $week) {
                    $this->assertTrue($week['starts_on']->equalTo($weeks[$i]['ends_on']->addDay()));
                }
            }
        }
    }

    /** RF-PLAN: solo la promotora accede a su planificación. */
    public function test_only_promoters_can_open_the_planning_page(): void
    {
        $this->actingAs($this->promoter())->get('/promoter/planning')
            ->assertOk()
            ->assertSee('Planificación')
            ->assertSee('Octubre de 2026');

        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->actingAs($admin)->get('/promoter/planning')->assertForbidden();
        Livewire::actingAs($admin)->test(Index::class)->assertForbidden();
    }

    /** RF-PLAN: un cronograma por promotora y mes. */
    public function test_creating_a_schedule_twice_keeps_a_single_one_with_its_weeks(): void
    {
        $promoter = $this->promoter();

        Livewire::actingAs($promoter)->test(Index::class)
            ->call('createSchedule')
            ->call('createSchedule')
            ->assertHasNoErrors()
            ->assertSee('Semana 5');

        $this->assertSame(1, MonthlySchedule::count());
        $schedule = MonthlySchedule::first();
        $this->assertSame(ScheduleStatus::BORRADOR, $schedule->status);
        $this->assertSame(5, $schedule->weeks()->count());
    }

    /** RF-PLAN: la restricción de unicidad también vive en la base de datos. */
    public function test_database_rejects_a_second_schedule_for_the_same_month(): void
    {
        $promoter = $this->promoter();
        $this->october($promoter);

        $this->expectException(UniqueConstraintViolationException::class);
        MonthlySchedule::create(['promoter_id' => $promoter->id, 'year' => 2026, 'month' => 10]);
    }

    public function test_cannot_create_a_schedule_for_a_past_month(): void
    {
        Livewire::actingAs($this->promoter())->test(Index::class)
            ->set('period', '2026-09')
            ->assertSee('Este mes ya pasó')
            ->call('createSchedule')
            ->assertHasErrors('period');

        $this->assertSame(0, MonthlySchedule::count());
    }

    /** RF-PLAN: la visita se ubica en la semana que corresponde a su fecha. */
    public function test_promoter_plans_a_visit_to_an_assigned_pharmacy(): void
    {
        $promoter = $this->promoter();
        $pharmacy = $this->pharmacy($promoter);
        $this->october($promoter);

        Livewire::actingAs($promoter)->test(Index::class)
            ->set('activityType', 'VISITA')
            ->set('scheduledDate', '2026-10-20')
            ->set('pharmacyId', (string) $pharmacy->id)
            ->set('notes', 'Revisar exhibición')
            ->call('addActivity')
            ->assertHasNoErrors()
            ->assertSee('Farmacia Central')
            ->assertViewHas('totalVisits', 1);

        $activity = PlannedActivity::with('week')->sole();
        $this->assertSame(ActivityType::VISITA, $activity->activity_type);
        $this->assertSame($pharmacy->id, $activity->pharmacy_id);
        $this->assertSame(4, $activity->week->week_number);
        $this->assertSame('Revisar exhibición', $activity->notes);
    }

    /** RF-PLAN: actividad tipo VISITA exige farmacia. */
    public function test_visit_without_pharmacy_is_rejected(): void
    {
        $promoter = $this->promoter();
        $this->october($promoter);

        Livewire::actingAs($promoter)->test(Index::class)
            ->set('activityType', 'VISITA')
            ->set('scheduledDate', '2026-10-20')
            ->call('addActivity')
            ->assertHasErrors(['pharmacyId' => 'required']);

        $this->assertSame(0, PlannedActivity::count());
    }

    /** RF-PLAN: la base de datos también impide una VISITA sin farmacia. */
    public function test_database_check_rejects_visit_without_pharmacy(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Los CHECK se ejercitan solo en MySQL.');
        }

        $week = $this->october($this->promoter())->weeks()->first();

        $this->expectException(QueryException::class);
        PlannedActivity::create([
            'planned_week_id' => $week->id,
            'activity_type' => ActivityType::VISITA,
            'scheduled_date' => '2026-10-02',
        ]);
    }

    /** RF-PLAN: la promotora no planifica farmacias ajenas, inactivas ni transferidas. */
    public function test_cannot_plan_pharmacies_outside_current_active_assignments(): void
    {
        $promoter = $this->promoter();
        $other = $this->promoter();
        $foreign = $this->pharmacy($other, 'Farmacia Ajena');
        $inactive = $this->pharmacy($promoter, 'Farmacia Cerrada', false);
        $transferred = $this->pharmacy($promoter, 'Farmacia Transferida');
        (new PharmacyAssignmentService)->reassign($transferred, $other);
        $this->october($promoter);

        $component = Livewire::actingAs($promoter)->test(Index::class)
            ->assertDontSee('Farmacia Ajena')
            ->assertDontSee('Farmacia Cerrada')
            ->assertDontSee('Farmacia Transferida');

        foreach ([$foreign, $inactive, $transferred] as $pharmacy) {
            $component->set('activityType', 'VISITA')
                ->set('scheduledDate', '2026-10-20')
                ->set('pharmacyId', (string) $pharmacy->id)
                ->call('addActivity')
                ->assertHasErrors('pharmacyId');
        }

        $this->assertSame(0, PlannedActivity::count());
    }

    public function test_non_visit_activities_do_not_need_a_pharmacy(): void
    {
        $promoter = $this->promoter();
        $pharmacy = $this->pharmacy($promoter);
        $this->october($promoter);

        Livewire::actingAs($promoter)->test(Index::class)
            ->set('activityType', 'FERIADO')
            ->set('scheduledDate', '2026-10-12')
            ->set('pharmacyId', (string) $pharmacy->id)
            ->call('addActivity')
            ->assertHasNoErrors()
            ->assertViewHas('otherActivities', 1);

        $activity = PlannedActivity::sole();
        $this->assertSame(ActivityType::FERIADO, $activity->activity_type);
        $this->assertNull($activity->pharmacy_id);
    }

    public function test_date_must_belong_to_the_schedule_month(): void
    {
        $promoter = $this->promoter();
        $pharmacy = $this->pharmacy($promoter);
        $this->october($promoter);

        Livewire::actingAs($promoter)->test(Index::class)
            ->set('scheduledDate', '2026-11-02')
            ->set('pharmacyId', (string) $pharmacy->id)
            ->call('addActivity')
            ->assertHasErrors('scheduledDate');

        $this->assertSame(0, PlannedActivity::count());
    }

    public function test_same_pharmacy_cannot_be_planned_twice_on_the_same_day(): void
    {
        $promoter = $this->promoter();
        $pharmacy = $this->pharmacy($promoter);
        $schedule = $this->october($promoter);
        $planner = new SchedulePlanner;
        $data = ['activity_type' => 'VISITA', 'scheduled_date' => '2026-10-20', 'pharmacy_id' => $pharmacy->id];

        $planner->addActivity($promoter, $schedule->id, $data);

        Livewire::actingAs($promoter)->test(Index::class)
            ->set('scheduledDate', '2026-10-20')
            ->set('pharmacyId', (string) $pharmacy->id)
            ->call('addActivity')
            ->assertHasErrors('pharmacyId');

        $planner->addActivity($promoter, $schedule->id, ['scheduled_date' => '2026-10-21'] + $data);
        $this->assertSame(2, PlannedActivity::count());
    }

    /** RF-PLAN: enviar cronograma; luego queda bloqueado. */
    public function test_submitted_schedule_is_locked(): void
    {
        $promoter = $this->promoter();
        $pharmacy = $this->pharmacy($promoter);
        $schedule = $this->october($promoter);

        $component = Livewire::actingAs($promoter)->test(Index::class)
            ->call('submit')
            ->assertHasErrors('submit');
        $this->assertTrue($schedule->fresh()->isDraft());

        $component->set('scheduledDate', '2026-10-20')
            ->set('pharmacyId', (string) $pharmacy->id)
            ->call('addActivity')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('Cronograma enviado')
            ->assertDontSee('Agregar actividad');

        $schedule->refresh();
        $this->assertSame(ScheduleStatus::ENVIADO, $schedule->status);
        $this->assertNotNull($schedule->submitted_at);

        $component->set('scheduledDate', '2026-10-21')
            ->set('pharmacyId', (string) $pharmacy->id)
            ->call('addActivity')
            ->assertHasErrors('submit')
            ->call('removeActivity', PlannedActivity::sole()->id)
            ->assertHasErrors('submit');

        $this->assertSame(1, PlannedActivity::count());
    }

    public function test_promoter_can_remove_an_activity_while_in_draft(): void
    {
        $promoter = $this->promoter();
        $schedule = $this->october($promoter);
        $activity = (new SchedulePlanner)->addActivity($promoter, $schedule->id, [
            'activity_type' => 'REUNION', 'scheduled_date' => '2026-10-16',
        ]);

        Livewire::actingAs($promoter)->test(Index::class)
            ->call('removeActivity', $activity->id)
            ->assertHasNoErrors();

        $this->assertSame(0, PlannedActivity::count());
    }

    /** RF-PLAN: una promotora no ve ni modifica el cronograma de otra. */
    public function test_promoter_cannot_see_or_touch_someone_elses_schedule(): void
    {
        $owner = $this->promoter();
        $intruder = $this->promoter();
        $pharmacy = $this->pharmacy($owner, 'Farmacia Privada');
        $schedule = $this->october($owner);
        $planner = new SchedulePlanner;
        $activity = $planner->addActivity($owner, $schedule->id, [
            'activity_type' => 'VISITA', 'scheduled_date' => '2026-10-20', 'pharmacy_id' => $pharmacy->id,
        ]);

        Livewire::actingAs($intruder)->test(Index::class)
            ->assertSee('No hay cronograma')
            ->assertDontSee('Farmacia Privada');

        try {
            Livewire::actingAs($intruder)->test(Index::class)->call('removeActivity', $activity->id);
            $this->fail('Una promotora ajena no debería eliminar actividades.');
        } catch (ModelNotFoundException) {
        }

        try {
            $planner->submit($intruder, $schedule->id);
            $this->fail('Una promotora ajena no debería enviar el cronograma.');
        } catch (ModelNotFoundException) {
        }

        $this->assertSame(1, PlannedActivity::count());
        $this->assertTrue($schedule->fresh()->isDraft());
    }
}
