<?php

namespace Tests\Feature;

use App\Livewire\Coordinator\FollowUp;
use App\Livewire\Coordinator\PendingNotice;
use App\Models\ActivityReschedule;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyChange;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReason;
use App\Services\CoordinatorFollowUp;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class CoordinatorFollowUpTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-15 16:00:00', 'UTC'));
    }

    public function test_attention_counts_match_destinations_and_the_existing_bell(): void
    {
        $actor = User::factory()->coordinator()->create();
        $people = User::factory()->promoter()->count(3)->create();
        $inactive = User::factory()->promoter()->create(['is_active' => false]);
        $shared = Pharmacy::factory()->create(['is_active' => true]);
        $covered = Pharmacy::factory()->create(['is_active' => true, 'name' => 'Fichero solo cobertura']);
        $unassigned = Pharmacy::factory()->create(['is_active' => true, 'name' => 'Fichero sin asignar']);
        Pharmacy::factory()->create(['is_active' => false, 'name' => 'Fichero inactivo']);
        foreach ($people->take(2) as $person) {
            PharmacyAssignment::factory()->create(['pharmacy_id' => $shared->id, 'promoter_id' => $person->id]);
        }
        PharmacyAssignment::factory()->create([
            'pharmacy_id' => $covered->id, 'promoter_id' => $people[0]->id, 'assignment_kind' => 'coverage',
            'coverage_starts_on' => '2026-11-01', 'coverage_ends_on' => '2026-11-30',
        ]);
        $draft = MonthlySchedule::create(['promoter_id' => $people[0]->id, 'year' => 2026, 'month' => 11, 'status' => 'BORRADOR']);
        MonthlySchedule::create(['promoter_id' => $people[1]->id, 'year' => 2026, 'month' => 11, 'status' => 'ENVIADO', 'submitted_at' => '2026-11-01 16:00:00']);
        MonthlySchedule::create(['promoter_id' => $people[2]->id, 'year' => 2026, 'month' => 10, 'status' => 'BORRADOR']);
        MonthlySchedule::create(['promoter_id' => $inactive->id, 'year' => 2026, 'month' => 11, 'status' => 'BORRADOR']);
        $recent = $this->requestFor($people[0], 'Alta reciente', '2026-11-12');
        $older = $this->requestFor($people[0], 'Alta anterior', '2026-11-02');
        $baja = PharmacyRequest::create(['promoter_id' => $people[0]->id, 'pharmacy_id' => $shared->id, 'type' => 'baja', 'status' => 'pending', 'reason' => 'Motivo de prueba']);

        Livewire::actingAs($actor)->test(PendingNotice::class)->assertViewHas('pending', 3);
        Livewire::actingAs($actor)->test(FollowUp::class)
            ->assertViewHas('counts', ['requests' => 3, 'schedules' => 1, 'establishments' => 2])
            ->assertSeeInOrder(['Alta anterior', 'Alta reciente'])
            ->assertSee(route('coordinator.requests.show', $older->id), false)
            ->assertSee(route('coordinator.requests.show', $recent->id), false)
            ->assertSee(route('coordinator.requests.show', $baja->id), false)
            ->set('pendingType', 'schedules')
            ->assertViewHas('items', fn ($items) => $items->count() === 1 && $items->items()[0]->id === $draft->id)
            ->set('pendingType', 'establishments')
            ->assertSee(route('coordinator.assignments.show', $unassigned->id), false);

        $this->actingAs($actor)->get(route('coordinator.schedules.index', ['mes' => '2026-11', 'estado' => 'borrador']))
            ->assertOk()->assertSee($people[0]->name)->assertDontSee($people[1]->name)->assertDontSee($inactive->name);
        $this->get(route('coordinator.establishments.index', CoordinatorFollowUp::ESTABLISHMENT_FILTERS))
            ->assertOk()->assertSee('Fichero solo cobertura')->assertSee('Fichero sin asignar')->assertDontSee('Fichero inactivo');
    }

    public function test_activity_uses_recorded_times_filters_and_independent_pagination(): void
    {
        $actor = User::factory()->coordinator()->create(['name' => 'Revisora registrada']);
        $person = User::factory()->promoter()->create(['name' => 'Promotora de prueba']);
        $site = Pharmacy::factory()->create(['name' => 'Farmacia de prueba']);
        $approved = $this->requestFor($person, 'Alta aprobada de prueba', '2026-11-09 05:59:59');
        $approved->update(['status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => '2026-11-13 17:00:00']);
        $rejected = $this->requestFor($person, 'Alta rechazada de prueba', '2026-11-09 06:00:00');
        $rejected->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'review_note' => 'Revisión de prueba', 'reviewed_at' => '2026-11-14 17:00:00']);
        $change = PharmacyChange::create(['pharmacy_id' => $site->id, 'actor_id' => $actor->id, 'before' => ['name' => 'Nombre anterior'], 'after' => ['name' => 'Nombre actual']]);
        $change->forceFill(['created_at' => '2026-11-12 17:00:00'])->save();
        for ($i = 0; $i < 10; $i++) {
            $visit = Visit::create(['idempotency_key' => (string) Str::uuid(), 'promoter_id' => $person->id, 'pharmacy_id' => $site->id,
                'status' => 'REALIZADA', 'visited_at' => sprintf('2026-11-15 %02d:00:00', $i + 6), 'latitude' => 12.1234, 'longitude' => -86.1234]);
        }

        Livewire::actingAs($actor)->test(FollowUp::class)
            ->set('activityType', 'visits')
            ->assertViewHas('events', fn ($events) => $events->count() === 8 && $events->hasMorePages() && $events->items()[0]->id === $visit->id)
            ->assertSee(route('coordinator.visits.show', $visit->id), false)
            ->assertDontSee('12.1234')
            ->call('nextPage', 'actividad')
            ->assertViewHas('events', fn ($events) => $events->count() === 2 && $events->currentPage() === 2)
            ->assertViewHas('items', fn ($items) => $items->currentPage() === 1)
            ->set('activityType', 'requests')->set('period', '7')
            ->assertViewHas('events', fn ($events) => $events->count() === 3 && $events->currentPage() === 1)
            ->assertSeeInOrder(['Solicitud rechazada', 'Solicitud aprobada', 'Solicitud de alta enviada'])
            ->assertSee('Revisora registrada')
            ->set('activityType', 'establishments')
            ->assertSee('#cambio-'.$change->id, false);

        $this->actingAs($actor)->get(route('coordinator.establishments.show', ['pharmacy' => $site->id, 'cambio' => $change->id]))
            ->assertOk()->assertSee('id="cambio-'.$change->id.'"', false)->assertSee('Nombre anterior');
    }

    public function test_assignment_and_schedule_events_use_the_recorded_actor_and_destination(): void
    {
        $actor = User::factory()->coordinator()->create(['name' => 'Coordinadora de prueba']);
        $person = User::factory()->promoter()->create();
        $site = Pharmacy::factory()->create();
        PharmacyAssignment::factory()->create([
            'pharmacy_id' => $site->id, 'promoter_id' => $person->id,
            'assigned_at' => '2026-11-10 16:00:00', 'ended_at' => '2026-11-12 16:00:00', 'ended_by' => $actor->id,
        ]);

        $schedule = MonthlySchedule::create(['promoter_id' => $person->id, 'year' => 2026, 'month' => 11,
            'status' => 'ENVIADO', 'submitted_at' => '2026-11-02 16:00:00']);
        $week = $schedule->weeks()->create(['week_number' => 1, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-08']);
        $activity = $week->activities()->create(['activity_type' => 'VISITA', 'pharmacy_id' => $site->id, 'scheduled_date' => '2026-11-05']);
        ActivityReschedule::create(['planned_activity_id' => $activity->id, 'actor_id' => $person->id,
            'from_date' => '2026-11-04', 'to_date' => '2026-11-05', 'visit_reason_id' => VisitReason::factory()->create()->id]);

        Livewire::actingAs($actor)->test(FollowUp::class)
            ->set('activityType', 'assignments')
            ->assertSeeInOrder(['Responsabilidad finalizada', 'Responsable asignada'])
            ->assertSee('Registró Coordinadora de prueba')
            ->assertSee(route('coordinator.assignments.show', $site->id), false)
            ->set('activityType', 'schedules')
            ->assertSeeInOrder(['Actividad reprogramada', 'Cronograma enviado'])
            ->assertSee(route('coordinator.schedules.show', $schedule->id), false);
    }

    public function test_follow_up_requires_an_active_coordinator_and_handles_empty_filters(): void
    {
        $this->get(route('coordinator.follow-up'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->promoter()->create())->get(route('coordinator.follow-up'))->assertForbidden();
        Livewire::actingAs(User::factory()->coordinator()->create(['is_active' => false]))
            ->test(FollowUp::class)->assertForbidden();
        $actor = User::factory()->coordinator()->create();
        $this->actingAs($actor)->get(route('coordinator.follow-up'))->assertOk()
            ->assertSee('No hay pendientes para este filtro')->assertSee('Sin actividad en este período');
        $this->get(route('coordinator.dashboard'))->assertOk()->assertDontSee('Bandeja de atención');
    }

    private function requestFor(User $promoter, string $name, string $date): PharmacyRequest
    {
        $request = PharmacyRequest::create(['promoter_id' => $promoter->id, 'type' => 'alta', 'status' => 'pending', 'reason' => 'Motivo de prueba', 'data' => ['name' => $name]]);
        $request->forceFill(['created_at' => $date])->save();

        return $request;
    }
}
