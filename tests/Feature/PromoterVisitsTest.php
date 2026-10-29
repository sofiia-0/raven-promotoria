<?php

namespace Tests\Feature;

use App\Enums\ActivityProgress;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Livewire\Promoter\Visits\History;
use App\Livewire\Promoter\Visits\Index;
use App\Livewire\Promoter\Visits\Register;
use App\Livewire\Promoter\Visits\Show;
use App\Models\ActivityReschedule;
use App\Models\AuditLog;
use App\Models\ChecklistItem;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReason;
use App\Services\SchedulePlanner;
use App\Services\VisitRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class PromoterVisitsTest extends TestCase
{
    use RefreshDatabase;

    private User $promoter;

    private Pharmacy $pharmacy;

    private VisitReason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        // Jueves 15/10/2026 06:00 en Managua.
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));

        $this->promoter = User::factory()->promoter()->create();
        $this->pharmacy = $this->assignedPharmacy($this->promoter, 'Farmacia Central');
        $this->reason = VisitReason::factory()->create(['name' => 'Establecimiento cerrado']);
    }

    private function assignedPharmacy(User $promoter, string $name): Pharmacy
    {
        $pharmacy = Pharmacy::factory()->create(['name' => $name]);
        PharmacyAssignment::factory()->create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $promoter->id]);

        return $pharmacy;
    }

    /** Cronograma de octubre con visitas el 14 (ayer), 15 (hoy) y 20 (futuro). */
    private function sentSchedule(?User $promoter = null, ?Pharmacy $pharmacy = null, bool $submit = true): MonthlySchedule
    {
        $promoter ??= $this->promoter;
        $pharmacy ??= $this->pharmacy;
        $planner = new SchedulePlanner;
        $schedule = $planner->create($promoter, 2026, 10);

        foreach (['2026-10-14', '2026-10-15', '2026-10-20'] as $date) {
            $planner->addActivity($promoter, $schedule->id, [
                'activity_type' => 'VISITA', 'scheduled_date' => $date, 'pharmacy_id' => $pharmacy->id,
            ]);
        }

        return $submit ? $planner->submit($promoter, $schedule->id) : $schedule;
    }

    private function activityOn(string $date): PlannedActivity
    {
        return PlannedActivity::query()->whereDate('scheduled_date', $date)->firstOrFail();
    }

    /** @param array<string,mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'REALIZADA',
            'planned_activity_id' => array_key_exists('planned_activity_id', $overrides)
                ? null : $this->activityOn('2026-10-15')->id,
        ];
    }

    /** RF-VIS: registro de visita planificada con checklist y ubicación. */
    public function test_promoter_registers_a_planned_visit_with_checklist_and_location(): void
    {
        $this->sentSchedule();
        $exhibit = ChecklistItem::factory()->create(['name' => 'Exhibición']);
        $stock = ChecklistItem::factory()->create(['name' => 'Inventario']);
        ChecklistItem::factory()->inactive()->create(['name' => 'Ítem retirado']);
        $activity = $this->activityOn('2026-10-15');

        Livewire::actingAs($this->promoter)
            ->test(Register::class, ['actividad' => $activity->id])
            ->assertSee('Farmacia Central')
            ->assertSee('Exhibición')
            ->assertDontSee('Ítem retirado')
            ->set('checklist.'.$exhibit->id, true)
            ->set('latitude', '12.1364000')
            ->set('longitude', '-86.2514000')
            ->set('locationAccuracy', 12)
            ->set('notes', 'Todo en orden')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('promoter.visits.show', ['visit' => Visit::sole()->id]));

        $visit = Visit::sole();
        $this->assertSame(VisitStatus::REALIZADA, $visit->status);
        $this->assertSame($activity->id, $visit->planned_activity_id);
        $this->assertSame($this->pharmacy->id, $visit->pharmacy_id);
        $this->assertSame(0, $visit->attempt);
        $this->assertSame('12.1364000', $visit->latitude);
        $this->assertTrue($visit->visited_at->equalTo(now()));

        // Una respuesta por ítem activo, marcado o no.
        $this->assertEqualsCanonicalizing(
            [$exhibit->id => true, $stock->id => false],
            $visit->checklist->pluck('checked', 'checklist_item_id')->all()
        );

        $this->assertSame(ActivityProgress::REALIZADA, $activity->fresh()->load('visits')->progress());
        $this->assertTrue(AuditLog::query()->where('action', 'visit.registered')->where('auditable_id', $visit->id)->exists());
    }

    /** RF-VIS: misma clave de idempotencia dos veces → un solo registro. */
    public function test_registering_twice_with_the_same_key_returns_the_existing_visit(): void
    {
        $this->sentSchedule();
        $recorder = new VisitRecorder;
        $payload = $this->payload();

        $first = $recorder->register($this->promoter, $payload);
        $second = $recorder->register($this->promoter, ['status' => 'NO_REALIZADA'] + $payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(VisitStatus::REALIZADA, $second->status);
        $this->assertSame(1, Visit::count());
        $this->assertSame(1, AuditLog::query()->where('action', 'visit.registered')->count());

        // Doble clic en el formulario: misma clave generada al abrirlo.
        $form = Livewire::actingAs($this->promoter)->test(Register::class)
            ->set('pharmacyId', (string) $this->pharmacy->id);
        $form->call('save')->assertHasNoErrors();
        $form->call('save')->assertHasNoErrors();
        $this->assertSame(2, Visit::count());
    }

    public function test_idempotency_key_of_another_promoter_is_not_disclosed(): void
    {
        $this->sentSchedule();
        $visit = (new VisitRecorder)->register($this->promoter, $this->payload());
        $other = User::factory()->promoter()->create();

        $this->expectException(ModelNotFoundException::class);
        (new VisitRecorder)->register($other, ['idempotency_key' => $visit->idempotency_key, 'status' => 'REALIZADA']);
    }

    /** RF-VIS: NO_REALIZADA exige motivo. */
    public function test_not_done_visit_requires_a_reason(): void
    {
        $this->sentSchedule();
        $activity = $this->activityOn('2026-10-15');

        Livewire::actingAs($this->promoter)
            ->test(Register::class, ['actividad' => $activity->id])
            ->set('status', 'NO_REALIZADA')
            ->call('save')
            ->assertHasErrors(['reasonId' => 'required']);

        $this->assertSame(0, Visit::count());

        Livewire::actingAs($this->promoter)
            ->test(Register::class, ['actividad' => $activity->id])
            ->set('status', 'NO_REALIZADA')
            ->set('reasonId', (string) $this->reason->id)
            ->set('checklist.1', true)
            ->call('save')
            ->assertHasNoErrors();

        $visit = Visit::sole();
        $this->assertSame(VisitStatus::NO_REALIZADA, $visit->status);
        $this->assertSame($this->reason->id, $visit->visit_reason_id);
        $this->assertCount(0, $visit->checklist);
    }

    /** RF-VIS: la base de datos también exige motivo en NO_REALIZADA. */
    public function test_database_check_rejects_not_done_visit_without_reason(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Los CHECK se ejercitan solo en MySQL.');
        }

        $this->expectException(QueryException::class);
        Visit::query()->create([
            'idempotency_key' => (string) Str::uuid(),
            'promoter_id' => $this->promoter->id,
            'pharmacy_id' => $this->pharmacy->id,
            'status' => VisitStatus::NO_REALIZADA,
            'visited_at' => now(),
        ]);
    }

    /** RF-VIS: la geolocalización es opcional. */
    public function test_visit_without_location_is_registered(): void
    {
        $this->sentSchedule();

        $visit = (new VisitRecorder)->register($this->promoter, $this->payload());

        $this->assertNull($visit->latitude);
        $this->assertNull($visit->longitude);
        $this->assertFalse($visit->hasLocation());
    }

    public function test_cannot_register_future_draft_or_already_registered_activities(): void
    {
        $this->sentSchedule();
        $recorder = new VisitRecorder;

        $future = $this->activityOn('2026-10-20');
        Livewire::actingAs($this->promoter)
            ->test(Register::class, ['actividad' => $future->id])
            ->assertSee('Si va a realizarla antes, reprográmela para hoy')
            ->assertDontSee('Registrar visita</button>', false);

        $recorder->register($this->promoter, $this->payload());
        try {
            $recorder->register($this->promoter, $this->payload());
            $this->fail('No debería registrarse dos veces la misma actividad.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('activity', $e->errors());
        }

        // Cronograma todavía en borrador (noviembre).
        $planner = new SchedulePlanner;
        $november = $planner->create($this->promoter, 2026, 11);
        $draft = $planner->addActivity($this->promoter, $november->id, [
            'activity_type' => 'VISITA', 'scheduled_date' => '2026-11-02', 'pharmacy_id' => $this->pharmacy->id,
        ]);
        try {
            $recorder->register($this->promoter, $this->payload(['planned_activity_id' => $draft->id]));
            $this->fail('No se registran visitas de un cronograma en borrador.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('activity', $e->errors());
        }

        $this->assertSame(1, Visit::count());
    }

    /** RF-VIS: PROMOTER no accede a datos ajenos. */
    public function test_promoter_cannot_register_or_see_someone_elses_visits(): void
    {
        $this->sentSchedule();
        $intruder = User::factory()->promoter()->create();
        $foreignPharmacy = $this->assignedPharmacy(User::factory()->promoter()->create(), 'Farmacia Ajena');
        $activity = $this->activityOn('2026-10-15');

        try {
            Livewire::actingAs($intruder)->test(Register::class, ['actividad' => $activity->id]);
            $this->fail('Una promotora ajena no debería abrir esta actividad.');
        } catch (ModelNotFoundException) {
        }

        try {
            (new VisitRecorder)->register($intruder, $this->payload());
            $this->fail('Una promotora ajena no debería registrar esta actividad.');
        } catch (ModelNotFoundException) {
        }

        Livewire::actingAs($this->promoter)->test(Register::class)
            ->assertDontSee('Farmacia Ajena')
            ->set('pharmacyId', (string) $foreignPharmacy->id)
            ->call('save')
            ->assertHasErrors('pharmacyId');

        $visit = (new VisitRecorder)->register($this->promoter, $this->payload());
        $this->actingAs($intruder)->get('/promoter/visits/'.$visit->id)->assertNotFound();
        Livewire::actingAs($intruder)->test(History::class)->assertDontSee('Farmacia Central');

        $this->assertSame(1, Visit::count());
    }

    /** RF-VIS: visita fuera de plan. */
    public function test_unplanned_visit_is_registered_and_flagged_in_history(): void
    {
        $other = $this->assignedPharmacy($this->promoter, 'Farmacia Sorpresa');

        Livewire::actingAs($this->promoter)->test(Register::class)
            ->assertSee('Fuera de plan')
            ->call('save')
            ->assertHasErrors(['pharmacyId' => 'required'])
            ->set('pharmacyId', (string) $other->id)
            ->call('save')
            ->assertHasNoErrors();

        $visit = Visit::sole();
        $this->assertTrue($visit->isUnplanned());
        $this->assertNull($visit->attempt);

        Livewire::actingAs($this->promoter)->test(History::class)
            ->assertSee('Farmacia Sorpresa')
            ->assertSee('Fuera de plan')
            ->set('origin', 'plan')
            ->assertDontSee('Farmacia Sorpresa');
    }

    /** RF-VIS: reprogramar tras no realizada abre un nuevo intento con rastro. */
    public function test_rescheduling_a_failed_visit_reopens_it_with_a_trail(): void
    {
        $this->sentSchedule();
        $recorder = new VisitRecorder;
        $activity = $this->activityOn('2026-10-15');

        $recorder->register($this->promoter, $this->payload([
            'status' => 'NO_REALIZADA', 'visit_reason_id' => $this->reason->id,
        ]));

        Livewire::actingAs($this->promoter)->test(Index::class)
            ->assertViewHas('failed', fn ($failed) => $failed->pluck('id')->all() === [$activity->id])
            ->call('startReschedule', $activity->id)
            ->set('rescheduleDate', '2026-10-16')
            ->call('saveReschedule')
            ->assertHasErrors(['rescheduleReasonId' => 'required'])
            ->set('rescheduleReasonId', (string) $this->reason->id)
            ->set('rescheduleNotes', 'Volver mañana')
            ->call('saveReschedule')
            ->assertHasNoErrors()
            ->assertViewHas('failed', fn ($failed) => $failed->isEmpty())
            ->assertViewHas('upcoming', fn ($upcoming) => $upcoming->contains('id', $activity->id));

        $activity->refresh();
        $this->assertSame('2026-10-16', $activity->scheduled_date->toDateString());
        $this->assertSame(1, $activity->reschedule_count);

        $trail = ActivityReschedule::sole();
        $this->assertSame('2026-10-15', $trail->from_date->toDateString());
        $this->assertSame('2026-10-16', $trail->to_date->toDateString());
        $this->assertTrue(AuditLog::query()->where('action', 'activity.rescheduled')->exists());

        // Al día siguiente se registra el segundo intento.
        $this->travelTo(CarbonImmutable::parse('2026-10-16 15:00:00', 'UTC'));
        $second = $recorder->register($this->promoter, $this->payload(['planned_activity_id' => $activity->id]));
        $this->assertSame(1, $second->attempt);
        $this->assertSame(ActivityProgress::REALIZADA, $activity->fresh()->load('visits')->progress());

        $this->actingAs($this->promoter)->get('/promoter/visits/'.$second->id)
            ->assertOk()
            ->assertSee('Reprogramaciones de esta visita')
            ->assertSee('Volver mañana');
    }

    public function test_reschedule_rules(): void
    {
        $this->sentSchedule();
        $planner = new SchedulePlanner;
        $today = $this->activityOn('2026-10-15');
        $future = $this->activityOn('2026-10-20');

        $cases = [
            [$future->id, '2026-10-14', 'anterior a hoy'],
            [$future->id, '2026-10-20', 'fecha distinta'],
            [$future->id, '2026-11-03', 'dentro del mes'],
            [$future->id, '2026-10-15', 'ya está planificada'],
        ];
        foreach ($cases as [$id, $date, $message]) {
            try {
                $planner->reschedule($this->promoter, $id, $date, $this->reason->id);
                $this->fail("Debió rechazar {$date}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString($message, $e->errors()['rescheduleDate'][0]);
            }
        }

        // Una visita realizada ya no se reprograma.
        (new VisitRecorder)->register($this->promoter, $this->payload());
        try {
            $planner->reschedule($this->promoter, $today->id, '2026-10-22', $this->reason->id);
            $this->fail('No debería reprogramarse una visita realizada.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ya fue realizada', $e->errors()['rescheduleDate'][0]);
        }

        // Una pendiente futura sí, sin necesidad de un intento fallido.
        $planner->reschedule($this->promoter, $future->id, '2026-10-21', $this->reason->id);
        $this->assertSame('2026-10-21', $future->fresh()->scheduled_date->toDateString());

        // Ajena: 404.
        $this->expectException(ModelNotFoundException::class);
        $planner->reschedule(User::factory()->promoter()->create(), $future->id, '2026-10-22', $this->reason->id);
    }

    public function test_draft_schedules_are_edited_in_planning_not_rescheduled(): void
    {
        $this->sentSchedule(submit: false);

        $this->expectException(ValidationException::class);
        (new SchedulePlanner)->reschedule($this->promoter, $this->activityOn('2026-10-20')->id, '2026-10-21', $this->reason->id);
    }

    public function test_agenda_groups_today_overdue_and_upcoming(): void
    {
        $this->sentSchedule();

        Livewire::actingAs($this->promoter)->test(Index::class)
            ->assertViewHas('dueToday', fn ($c) => $c->count() === 1)
            ->assertViewHas('overdue', fn ($c) => $c->count() === 1)
            ->assertViewHas('upcoming', fn ($c) => $c->count() === 1)
            ->assertViewHas('plannedThisMonth', 3)
            ->assertSee('Farmacia Central');

        // Lo de otra promotora no aparece.
        Livewire::actingAs(User::factory()->promoter()->create())->test(Index::class)
            ->assertViewHas('plannedThisMonth', 0);
    }

    /** RF-VIS: historial filtrado por día de Managua, no por día UTC. */
    public function test_history_filters_by_managua_dates_and_status(): void
    {
        $this->sentSchedule();
        $recorder = new VisitRecorder;

        // 15/10 23:30 en Managua = 16/10 05:30 UTC.
        $this->travelTo(CarbonImmutable::parse('2026-10-16 05:30:00', 'UTC'));
        $recorder->register($this->promoter, $this->payload());
        $recorder->register($this->promoter, [
            'idempotency_key' => (string) Str::uuid(), 'status' => 'NO_REALIZADA',
            'pharmacy_id' => $this->assignedPharmacy($this->promoter, 'Farmacia Cerrada')->id,
            'visit_reason_id' => $this->reason->id,
        ]);

        Livewire::actingAs($this->promoter)->test(History::class)
            ->set('from', '2026-10-15')
            ->set('to', '2026-10-15')
            ->assertViewHas('visits', fn ($v) => $v->total() === 2)
            ->set('status', 'NO_REALIZADA')
            ->assertViewHas('visits', fn ($v) => $v->total() === 1)
            ->assertSee('Farmacia Cerrada')
            ->set('status', '')
            ->set('from', '2026-10-16')
            ->set('to', '2026-10-16')
            ->assertViewHas('visits', fn ($v) => $v->total() === 0);
    }

    public function test_visit_detail_shows_checklist(): void
    {
        $this->sentSchedule();
        $item = ChecklistItem::factory()->create(['name' => 'Precios visibles']);
        $visit = (new VisitRecorder)->register($this->promoter, $this->payload(['checklist' => [$item->id => true]]));

        Livewire::actingAs($this->promoter)->test(Show::class, ['visitId' => $visit->id])
            ->assertSee('Precios visibles')
            ->assertSee('1 de 1 actividades registradas');
    }

    public function test_audit_log_is_append_only_and_records_schedule_submission(): void
    {
        $this->sentSchedule();

        $log = AuditLog::query()->where('action', 'schedule.submitted')->sole();
        $this->assertSame($this->promoter->id, $log->actor_id);
        $this->assertSame('2026-10', $log->details['period']);

        try {
            $log->update(['action' => 'otra']);
            $this->fail('La bitácora no debería modificarse.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_only_promoters_reach_the_visit_pages(): void
    {
        $this->sentSchedule();
        $this->actingAs($this->promoter)->get('/promoter/visits')->assertOk()->assertSee('Para hoy');
        $this->actingAs($this->promoter)->get('/promoter/visits/history')->assertOk();
        $this->actingAs($this->promoter)
            ->get('/promoter/visits/register?actividad='.$this->activityOn('2026-10-15')->id)
            ->assertOk()
            ->assertSee('Visita planificada');

        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        foreach (['/promoter/visits', '/promoter/visits/history', '/promoter/visits/register'] as $url) {
            $this->actingAs($admin)->get($url)->assertForbidden();
        }
    }
}
