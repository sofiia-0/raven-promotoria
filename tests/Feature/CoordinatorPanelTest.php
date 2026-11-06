<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Livewire\Coordinator\Dashboard;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReason;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class CoordinatorPanelTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Jueves 15/10/2026 06:00 en Managua.
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));
    }

    private function visit(User $promoter, Pharmacy $pharmacy, VisitStatus $status, string $visitedAt): Visit
    {
        return Visit::query()->create([
            'idempotency_key' => (string) Str::uuid(),
            'promoter_id' => $promoter->id,
            'pharmacy_id' => $pharmacy->id,
            'status' => $status,
            'visit_reason_id' => $status === VisitStatus::NO_REALIZADA ? VisitReason::factory()->create()->id : null,
            'visited_at' => CarbonImmutable::parse($visitedAt, 'UTC'),
        ]);
    }

    /** RNF-SEG: solo una Coordinadora activa entra al panel. */
    public function test_only_active_coordinators_reach_the_panel(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $this->actingAs($coordinator)->get('/coordinator')
            ->assertOk()
            ->assertSee('Panel de Coordinación')
            ->assertSee('Coordinadora');
        $this->get('/')->assertRedirect(route('coordinator.dashboard'));

        foreach ([UserRole::PROMOTER, UserRole::ADMIN] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->get('/coordinator')->assertForbidden();
        }
        $this->actingAs(User::factory()->coordinator()->inactive()->create())->get('/coordinator')->assertForbidden();

        // Livewire revalida en cada petición, aunque no pase por el middleware de la ruta.
        Livewire::actingAs(User::factory()->promoter()->create())->test(Dashboard::class)->assertForbidden();
    }

    /** RF-TAB: el tablero muestra conteos reales del mes en curso. */
    public function test_dashboard_counts_requests_assignments_schedules_and_visits(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        [$ana, $bea, $cris] = User::factory()->promoter()->count(3)->create()->all();
        User::factory()->promoter()->inactive()->create();

        $assigned = Pharmacy::factory()->create();
        PharmacyAssignment::factory()->create(['pharmacy_id' => $assigned->id, 'promoter_id' => $ana->id]);
        Pharmacy::factory()->count(2)->create(); // activas sin promotora
        Pharmacy::factory()->inactive()->create();
        $ended = Pharmacy::factory()->create();
        PharmacyAssignment::factory()->ended()->create(['pharmacy_id' => $ended->id, 'promoter_id' => $bea->id]);

        PharmacyRequest::query()->create(['promoter_id' => $ana->id, 'type' => 'alta', 'status' => 'pending', 'data' => ['name' => 'Farmacia Propuesta'], 'reason' => 'Nuevo punto de venta']);
        PharmacyRequest::query()->create(['promoter_id' => $ana->id, 'pharmacy_id' => $assigned->id, 'type' => 'baja', 'status' => 'pending', 'reason' => 'Cerró definitivamente']);
        PharmacyRequest::query()->create(['promoter_id' => $ana->id, 'type' => 'alta', 'status' => 'rejected', 'data' => ['name' => 'Rechazada'], 'reason' => 'x', 'reviewed_by' => $coordinator->id, 'reviewed_at' => now(), 'review_note' => 'Duplicada']);

        $planner = new SchedulePlanner;
        $sent = $planner->create($ana, 2026, 10);
        $planner->addActivity($ana, $sent->id, ['activity_type' => 'REUNION', 'scheduled_date' => '2026-10-20']);
        $planner->submit($ana, $sent->id);
        $planner->create($bea, 2026, 10); // borrador; Cris no tiene cronograma

        $this->visit($ana, $assigned, VisitStatus::REALIZADA, '2026-10-02 15:00:00');
        $this->visit($ana, $assigned, VisitStatus::NO_REALIZADA, '2026-10-14 20:00:00');
        $this->visit($ana, $assigned, VisitStatus::REALIZADA, '2026-10-01 04:00:00'); // 30/09 22:00 en Managua: fuera del mes

        Livewire::actingAs($coordinator)->test(Dashboard::class)
            ->assertViewHas('pendingAlta', 1)
            ->assertViewHas('pendingBaja', 1)
            ->assertViewHas('activePharmacies', 4)
            ->assertViewHas('unassigned', 3)
            ->assertViewHas('activePromoters', 3)
            ->assertViewHas('schedulesSent', 1)
            ->assertViewHas('schedulesDraft', 1)
            ->assertViewHas('schedulesMissing', 1)
            ->assertViewHas('visitsDone', 1)
            ->assertViewHas('visitsNotDone', 1)
            ->assertViewHas('visitsUnplanned', 2)
            ->assertSee('Farmacia Propuesta')
            ->assertDontSee('Rechazada');
    }
}
