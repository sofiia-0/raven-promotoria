<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Metrics;
use App\Livewire\Coordinator\PendingNotice;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Models\Visit;
use App\Services\CoordinatorActivityMetrics;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class CoordinatorMetricsTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private function historicalVisit(User $actor, Pharmacy $pharmacy, array $extra = []): Visit
    {
        return Visit::create(array_merge(['idempotency_key' => (string) Str::uuid(), 'promoter_id' => $actor->id, 'pharmacy_id' => $pharmacy->id, 'status' => 'REALIZADA', 'visited_at' => '2026-11-10 16:00:00'], $extra));
    }

    public function test_shared_locations_are_counted_once_but_individual_plan_activities_are_not(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'UTC'));
        $coordinator = User::factory()->coordinator()->create();
        $people = User::factory()->promoter()->count(2)->sequence(['name' => 'Ana'], ['name' => 'Bea'])->create();
        $shared = Pharmacy::factory()->create(['department' => 'Managua', 'category' => 'A']);
        $outside = Pharmacy::factory()->create(['department' => 'León', 'category' => 'B']);
        $planner = new SchedulePlanner;
        $first = null;
        foreach ($people as $person) {
            PharmacyAssignment::factory()->create(['promoter_id' => $person->id, 'pharmacy_id' => $shared->id]);
            $schedule = $planner->create($person, 2026, 11);
            $activity = $planner->addActivity($person, $schedule->id, ['scheduled_date' => '2026-11-02', 'activity_type' => 'VISITA', 'pharmacy_id' => $shared->id]);
            $planner->submit($person, $schedule->id);
            $first ??= $activity;
        }
        $this->historicalVisit($people[0], $shared, ['planned_activity_id' => $first->id, 'attempt' => 0]);
        $this->historicalVisit($people[0], $shared);
        $this->historicalVisit($people[0], $outside);

        $summary = app(CoordinatorActivityMetrics::class)->forMonth($coordinator, 2026, 11);

        $this->assertSame(1, $summary['scheduledEstablishments']);
        $this->assertSame(1, $summary['coveredEstablishments']);
        $this->assertSame(2, $summary['visitedEstablishments']);
        $this->assertSame(100.0, $summary['coverage']);
        $this->assertSame(2, $summary['scheduledVisits']);
        $this->assertSame(1, $summary['completedPlanned']);
        $this->assertSame(50.0, $summary['compliance']);
        $this->assertSame(3, $summary['totalRealized']);
        $this->assertSame([2, 1], array_column($summary['categories'], 'total'));
        $this->assertSame([100.0, 0.0], array_column($summary['promoters'], 'coverage'));
        $filtered = app(CoordinatorActivityMetrics::class)->forMonth($coordinator, 2026, 11, ['promoter' => (string) $people[1]->id]);
        $this->assertSame(0, $filtered['totalRealized']);
        Livewire::actingAs($coordinator)->test(Metrics::class)->assertSee('Evolución de seis meses')->assertSee('Cumplimiento por promotora');
    }

    public function test_period_boundaries_and_commercial_filters_do_not_include_other_months(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $person = User::factory()->promoter()->create();
        $pharmacy = Pharmacy::factory()->create(['department' => 'León', 'category' => 'A']);
        foreach (['2026-11-01 05:59:59', '2026-11-01 06:00:00', '2026-12-01 05:59:59', '2026-12-01 06:00:00'] as $date) {
            $this->historicalVisit($person, $pharmacy, ['visited_at' => $date]);
        }
        $summary = app(CoordinatorActivityMetrics::class)->forMonth($coordinator, 2026, 11, ['department' => 'León', 'category' => 'A']);
        $this->assertSame(2, $summary['totalRealized']);
        $this->assertNull($summary['coverage']);
        $this->assertNull($summary['compliance']);
        $this->assertSame(0, app(CoordinatorActivityMetrics::class)->forMonth($coordinator, 2026, 11, ['department' => 'Managua'])['totalRealized']);
    }

    public function test_empty_metrics_show_no_percentage_and_pending_notice_uses_real_requests(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'UTC'));
        $actor = User::factory()->coordinator()->create();
        Livewire::actingAs($actor)->test(Metrics::class)->assertSee('—')->assertSee('No hay visitas realizadas para este período.');
        Livewire::actingAs($actor)->test(PendingNotice::class)->assertSee('No hay solicitudes pendientes.');
        $request = PharmacyRequest::create(['promoter_id' => User::factory()->promoter()->create()->id, 'type' => 'alta', 'status' => 'pending', 'reason' => 'Justificación de demostración', 'data' => ['name' => 'Establecimiento ficticio']]);
        Livewire::actingAs($actor)->test(PendingNotice::class)->assertSee('Establecimiento ficticio')->assertSee(route('coordinator.requests.show', $request->id), false)->assertSee('Ver todas las solicitudes');

        $this->actingAs($actor)->get(route('coordinator.dashboard'))
            ->assertOk()->assertSee(route('coordinator.requests.show', $request->id), false)
            ->assertDontSee('Solicitudes en espera')->assertDontSee('Seguimiento operativo');
        $this->get(route('coordinator.requests.show', $request->id))
            ->assertOk()->assertSee('Establecimiento ficticio')->assertDontSee('Filtrar solicitudes');
        $this->get(route('coordinator.requests.show', $request->id + 1))->assertNotFound();
        $this->actingAs($request->promoter)->get(route('coordinator.requests.show', $request->id))->assertForbidden();
    }

    public function test_gps_and_month_comparison_use_the_same_scope_and_hide_open_period_deltas(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-15 12:00:00', 'UTC'));
        $coordinator = User::factory()->coordinator()->create();
        $person = User::factory()->promoter()->create();
        $pharmacy = Pharmacy::factory()->create(['department' => 'León', 'category' => 'A']);
        $elsewhere = Pharmacy::factory()->create(['department' => 'Managua', 'category' => 'AAA']);
        $coordinates = ['latitude' => 12.434, 'longitude' => -86.877];
        $this->historicalVisit($person, $pharmacy, $coordinates + ['visited_at' => '2026-10-10 16:00:00']);
        $this->historicalVisit($person, $pharmacy, $coordinates);
        $this->historicalVisit($person, $pharmacy);
        $this->historicalVisit($person, $elsewhere, $coordinates);

        $summary = app(CoordinatorActivityMetrics::class)->forMonth($coordinator, 2026, 11, ['department' => 'León']);
        $this->assertSame(1, $summary['geolocatedVisits']);
        $this->assertSame(2, $summary['totalRealized']);

        Livewire::actingAs($coordinator)->test(Metrics::class)
            ->assertViewHas('comparison', null)
            ->set('department', 'León')
            ->set('month', 11)
            ->assertViewHas('comparison', fn ($previous) => $previous['totalRealized'] === 1)
            ->assertSee('1 de 2 visitas realizadas con GPS válido')
            ->assertSee('+1')
            ->assertDontSee('12.434');
    }

    public function test_dashboard_counts_current_responsibilities_once_and_filters_the_six_month_series(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-15 12:00:00', 'UTC'));
        $actor = User::factory()->coordinator()->create();
        $people = User::factory()->promoter()->count(2)->create();
        $shared = Pharmacy::factory()->create(['department' => 'León', 'category' => 'A', 'is_active' => true]);
        $coverage = Pharmacy::factory()->create(['department' => 'León', 'category' => 'A', 'is_active' => true]);
        $other = Pharmacy::factory()->create(['department' => 'Managua', 'category' => 'AAA', 'is_active' => true]);
        $inactive = Pharmacy::factory()->create(['department' => 'León', 'category' => 'A', 'is_active' => false]);
        foreach ($people as $person) {
            PharmacyAssignment::factory()->create(['pharmacy_id' => $shared->id, 'promoter_id' => $person->id]);
        }
        PharmacyAssignment::factory()->create(['pharmacy_id' => $inactive->id, 'promoter_id' => $people[0]->id]);
        PharmacyAssignment::factory()->create([
            'pharmacy_id' => $coverage->id, 'promoter_id' => $people[0]->id, 'assignment_kind' => 'coverage',
            'coverage_starts_on' => '2026-12-01', 'coverage_ends_on' => '2026-12-31',
        ]);

        $this->historicalVisit($people[0], $shared, ['visited_at' => '2026-06-01 06:00:00']);
        $this->historicalVisit($people[0], $shared, ['visited_at' => '2026-06-01 05:59:59']);
        $this->historicalVisit($people[0], $shared, ['visited_at' => '2026-11-01 05:59:59']);
        $this->historicalVisit($people[1], $shared);
        $this->historicalVisit($people[0], $other);

        $service = app(CoordinatorActivityMetrics::class);
        $overview = $service->overview($actor, 2026, 11);
        $this->assertSame(['active' => 3, 'responsible' => 1], $overview['roster']);
        $this->assertSame([1, 0, 0, 0, 1, 2], array_column($overview['trend'], 'realized'));

        $filters = ['promoter' => (string) $people[0]->id, 'department' => 'León', 'category' => 'A'];
        $filtered = $service->overview($actor, 2026, 11, $filters);
        $this->assertSame(['active' => 2, 'responsible' => 1], $filtered['roster']);
        $this->assertSame([1, 0, 0, 0, 1, 0], array_column($filtered['trend'], 'realized'));
        $this->assertSame(0, $filtered['summary']['visitedEstablishments']);
        $this->assertSame(1, $filtered['comparison']['totalRealized']);

        Livewire::actingAs($actor)->test(Metrics::class)
            ->set('month', 11)->set('promoter', (string) $people[0]->id)->set('department', 'León')->set('category', 'A')
            ->assertViewHas('roster', $filtered['roster'])
            ->assertSee('Fichero y alcance')
            ->assertSee('Ver cifras mensuales')
            ->assertDontSee('Explorar mapa de visitas');
    }

    public function test_non_coordinator_roles_cannot_consult_team_metrics_or_notices(): void
    {
        foreach ([UserRole::PROMOTER, UserRole::ADMIN] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            Livewire::actingAs($actor)->test(Metrics::class)->assertForbidden();
            Livewire::actingAs($actor)->test(PendingNotice::class)->assertForbidden();
        }
        $actor = User::factory()->coordinator()->create(['is_active' => false]);
        Livewire::actingAs($actor)->test(Metrics::class)->assertForbidden();
    }
}
