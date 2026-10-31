<?php

namespace Tests\Feature;

use App\Livewire\Coordinator\Assignments\Index;
use App\Livewire\Coordinator\Assignments\Show;
use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Services\PharmacyAssignmentService;
use App\Services\SchedulePlanner;
use App\Services\VisitRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class CoordinatorAssignmentsTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $coordinator;

    private User $ana;

    private User $bea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));
        $this->coordinator = User::factory()->coordinator()->create();
        $this->ana = User::factory()->promoter()->create(['name' => 'Ana Promotora']);
        $this->bea = User::factory()->promoter()->create(['name' => 'Bea Promotora']);
    }

    private function assignedTo(User $promoter, string $name): Pharmacy
    {
        $pharmacy = Pharmacy::factory()->create(['name' => $name]);
        PharmacyAssignment::factory()->create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $promoter->id]);

        return $pharmacy;
    }

    /** RF-FAR: asignar en lote farmacias sin promotora. */
    public function test_coordinator_assigns_unassigned_pharmacies_in_batch(): void
    {
        $first = Pharmacy::factory()->create(['name' => 'Farmacia Uno']);
        $second = Pharmacy::factory()->create(['name' => 'Farmacia Dos']);
        $this->assignedTo($this->bea, 'Farmacia Ajena');

        $this->actingAs($this->coordinator)->get('/coordinator/assignments')->assertOk()->assertSee('Carga por promotora');

        Livewire::actingAs($this->coordinator)->test(Index::class)
            ->set('promoter', 'none')
            ->assertViewHas('pharmacies', fn ($p) => $p->total() === 2)
            ->call('selectPage', [$first->id, $second->id])
            ->assertSet('selected', [(string) $first->id, (string) $second->id])
            ->set('targetPromoterId', (string) $this->ana->id)
            ->call('assignSelected')
            ->assertHasNoErrors()
            ->assertSee('Se asignaron 2 establecimientos a Ana Promotora.')
            ->assertSet('selected', [])
            ->assertViewHas('unassignedCount', 0)
            ->assertViewHas('promoters', fn ($p) => $p->firstWhere('id', $this->ana->id)->active_pharmacies === 2);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->ana->assignedPharmacies()->pluck('pharmacies.id')->all());
        $this->assertSame(2, AuditLog::query()->where('action', 'assignment.changed')->where('actor_id', $this->coordinator->id)->count());
    }

    /** RF-FAR: reasignar conserva el historial y avisa de las visitas planificadas afectadas. */
    public function test_reassignment_keeps_history_and_reports_affected_planned_visits(): void
    {
        $pharmacy = $this->assignedTo($this->ana, 'Farmacia Central');
        $planner = new SchedulePlanner;
        $schedule = $planner->create($this->ana, 2026, 10);
        $future = $planner->addActivity($this->ana, $schedule->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-10-15', 'pharmacy_id' => $pharmacy->id]);
        $planner->addActivity($this->ana, $schedule->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-10-22', 'pharmacy_id' => $pharmacy->id]);
        $planner->submit($this->ana, $schedule->id);

        Livewire::actingAs($this->coordinator)->test(Index::class)
            ->set('selected', [(string) $pharmacy->id])
            ->set('targetPromoterId', (string) $this->bea->id)
            ->call('assignSelected')
            ->assertHasNoErrors()
            ->assertSee('2 visitas planificadas de la promotora anterior ya no podrán registrarse.');

        $this->assertSame($this->bea->id, $pharmacy->fresh()->currentAssignment->promoter_id);
        $this->assertSame(2, $pharmacy->assignments()->count());
        $this->assertNotNull($pharmacy->assignments()->where('promoter_id', $this->ana->id)->value('ended_at'));

        // La promotora anterior ya no puede registrar la visita que tenía planificada hoy.
        try {
            (new VisitRecorder)->register($this->ana, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'planned_activity_id' => $future->id]);
            $this->fail('La promotora anterior no debería registrar visitas de una farmacia reasignada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pharmacyId', $e->errors());
        }

        $this->actingAs($this->coordinator)->get('/coordinator/assignments/'.$pharmacy->id)
            ->assertOk()
            ->assertSee('Ana Promotora')
            ->assertSee('Bea Promotora')
            ->assertSee('Terminada')
            ->assertSee('Se cambió la promotora');
    }

    public function test_assigning_to_the_same_promoter_is_idempotent(): void
    {
        $pharmacy = $this->assignedTo($this->ana, 'Farmacia Central');

        $result = (new PharmacyAssignmentService)->assignMany($this->coordinator, [$pharmacy->id, $pharmacy->id], $this->ana);

        $this->assertSame(['changed' => 0, 'unchanged' => 1, 'pending_visits' => 0], $result);
        $this->assertSame(1, $pharmacy->assignments()->count());
        $this->assertSame(0, AuditLog::count());
    }

    /** RF-FAR: quitar la asignación deja la ficha sin promotora y conserva el historial. */
    public function test_unassigning_ends_the_current_assignment(): void
    {
        $pharmacy = $this->assignedTo($this->ana, 'Farmacia Central');

        Livewire::actingAs($this->coordinator)->test(Show::class, ['pharmacyId' => $pharmacy->id])
            ->call('unassign')
            ->assertSee('quedó sin promotora');

        $this->assertNull($pharmacy->fresh()->currentAssignment);
        $this->assertSame(1, $pharmacy->assignments()->count());
        $this->assertFalse($this->ana->assignedPharmacies()->whereKey($pharmacy->id)->exists());
        $this->assertSame(1, AuditLog::query()->where('action', 'assignment.ended')->count());

        Livewire::actingAs($this->coordinator)->test(Index::class)
            ->set('promoter', 'none')
            ->assertSee('Farmacia Central');
    }

    public function test_inactive_pharmacies_and_promoters_are_rejected(): void
    {
        $inactivePharmacy = Pharmacy::factory()->inactive()->create();
        $active = Pharmacy::factory()->create();
        $inactivePromoter = User::factory()->promoter()->inactive()->create();
        $service = new PharmacyAssignmentService;

        try {
            $service->assignMany($this->coordinator, [$active->id, $inactivePharmacy->id], $this->ana);
            $this->fail('Una farmacia inactiva no debería asignarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('selection', $e->errors());
        }
        $this->assertNull($active->fresh()->currentAssignment); // Todo o nada.

        try {
            $service->assignMany($this->coordinator, [$active->id], $inactivePromoter);
            $this->fail('Una promotora inactiva no debería recibir asignaciones.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('targetPromoterId', $e->errors());
        }

        Livewire::actingAs($this->coordinator)->test(Index::class)
            ->call('assignSelected')
            ->assertHasErrors(['selected' => 'required', 'targetPromoterId' => 'required']);

        $this->assertSame(0, PharmacyAssignment::count());
    }

    /** RNF-SEG: una promotora no reparte farmacias. */
    public function test_promoters_cannot_change_assignments(): void
    {
        $pharmacy = Pharmacy::factory()->create();

        try {
            (new PharmacyAssignmentService)->assignMany($this->ana, [$pharmacy->id], $this->ana);
            $this->fail('Una promotora no debería asignarse farmacias.');
        } catch (AccessDeniedHttpException) {
        }

        $this->actingAs($this->ana)->get('/coordinator/assignments')->assertForbidden();
        $this->actingAs($this->ana)->get('/coordinator/assignments/'.$pharmacy->id)->assertForbidden();
        Livewire::actingAs($this->ana)->test(Index::class)->assertForbidden();
        $this->assertSame(0, PharmacyAssignment::count());
    }

    public function test_filters_by_search_department_and_promoter(): void
    {
        $this->assignedTo($this->ana, 'Farmacia San Rafael')->update(['department' => 'Masaya', 'city' => 'Masaya']);
        $this->assignedTo($this->bea, 'Farmacia El Carmen')->update(['department' => 'Managua', 'city' => 'Managua']);
        Pharmacy::factory()->create(['name' => 'Farmacia Sin Dueña', 'department' => 'Managua']);

        Livewire::actingAs($this->coordinator)->test(Index::class)
            ->assertViewHas('pharmacies', fn ($p) => $p->total() === 3)
            ->set('promoter', (string) $this->bea->id)
            ->assertViewHas('pharmacies', fn ($p) => $p->total() === 1 && $p->first()->name === 'Farmacia El Carmen')
            ->set('promoter', '')
            ->set('department', 'Managua')
            ->assertViewHas('pharmacies', fn ($p) => $p->total() === 2)
            ->set('search', 'dueña')
            ->assertViewHas('pharmacies', fn ($p) => $p->total() === 1 && $p->first()->name === 'Farmacia Sin Dueña')
            ->call('clearFilters')
            ->assertViewHas('pharmacies', fn ($p) => $p->total() === 3);
    }
}
