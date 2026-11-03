<?php

namespace Tests\Feature;

use App\Livewire\Coordinator\Requests\Index;
use App\Livewire\Promoter\PharmacyRequests\Index as PromoterRequests;
use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyRequest;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Services\PharmacyRequestReview;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class CoordinatorPharmacyRequestsTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $coordinator;

    private User $promoter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));
        $this->coordinator = User::factory()->coordinator()->create();
        $this->promoter = User::factory()->promoter()->create(['name' => 'Ana Promotora']);
    }

    private function alta(array $data = []): PharmacyRequest
    {
        return PharmacyRequest::query()->create([
            'promoter_id' => $this->promoter->id,
            'type' => PharmacyRequest::TYPE_ALTA,
            'status' => PharmacyRequest::PENDING,
            'data' => $data + [
                'name' => 'Farmacia Nueva Esperanza', 'client_type' => 'FARMACIA INDEPENDIENTE', 'category' => 'AA',
                'department' => 'Managua', 'city' => 'Managua', 'address' => 'Del parque 2 c. al sur', 'phone' => null, 'email' => null,
            ],
            'reason' => 'Establecimiento nuevo en la zona',
        ]);
    }

    private function assignedPharmacy(string $name = 'Farmacia Central'): Pharmacy
    {
        $pharmacy = Pharmacy::factory()->create(['name' => $name, 'department' => 'Managua', 'city' => 'Managua']);
        PharmacyAssignment::factory()->create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $this->promoter->id]);

        return $pharmacy;
    }

    private function baja(Pharmacy $pharmacy): PharmacyRequest
    {
        return PharmacyRequest::query()->create([
            'promoter_id' => $this->promoter->id,
            'pharmacy_id' => $pharmacy->id,
            'type' => PharmacyRequest::TYPE_BAJA,
            'status' => PharmacyRequest::PENDING,
            'reason' => 'El establecimiento cerró definitivamente',
        ]);
    }

    /** RF-AB: aprobar un alta crea la ficha y la asigna a la promotora solicitante. */
    public function test_approving_an_alta_creates_and_assigns_the_pharmacy(): void
    {
        $request = $this->alta(['catalog_proposals' => ['category' => 'AA']]);

        $this->actingAs($this->coordinator)->get('/coordinator/requests')->assertOk()->assertSee('Farmacia Nueva Esperanza');

        Livewire::actingAs($this->coordinator)->test(Index::class)
            ->call('select', $request->id)
            ->assertSee('Valor nuevo propuesto')
            ->assertSet('assigneeId', (string) $this->promoter->id)
            ->set('reviewNote', 'Datos verificados por teléfono')
            ->call('approve')
            ->assertHasNoErrors()
            ->assertSee('Alta aprobada');

        $pharmacy = Pharmacy::query()->where('name', 'Farmacia Nueva Esperanza')->sole();
        $this->assertTrue($pharmacy->is_active);
        $this->assertSame('AA', $pharmacy->category);
        $this->assertSame('Del parque 2 c. al sur', $pharmacy->address);
        $this->assertSame($this->promoter->id, $pharmacy->currentAssignment->promoter_id);
        $this->assertTrue($this->promoter->assignedPharmacies()->whereKey($pharmacy->id)->exists());

        $request->refresh();
        $this->assertSame(PharmacyRequest::APPROVED, $request->status);
        $this->assertSame($pharmacy->id, $request->pharmacy_id);
        $this->assertSame($this->coordinator->id, $request->reviewed_by);
        $this->assertTrue($request->reviewed_at->equalTo(now()));
        $this->assertSame('Datos verificados por teléfono', $request->review_note);

        $log = AuditLog::query()->where('action', 'pharmacy_request.approved')->sole();
        $this->assertSame($this->coordinator->id, $log->actor_id);
        $this->assertSame($pharmacy->id, $log->details['pharmacy_id']);
    }

    /** RF-AB: la Coordinadora puede asignar el alta a otra promotora activa, nunca a una inactiva. */
    public function test_alta_can_be_assigned_to_another_active_promoter_only(): void
    {
        $other = User::factory()->promoter()->create();
        $inactive = User::factory()->promoter()->inactive()->create();
        $request = $this->alta();

        Livewire::actingAs($this->coordinator)->test(Index::class)
            ->call('select', $request->id)
            ->assertDontSee($inactive->name)
            ->set('assigneeId', (string) $inactive->id)
            ->call('approve')
            ->assertHasErrors('assigneeId');
        $this->assertSame(0, Pharmacy::count());
        $this->assertSame(PharmacyRequest::PENDING, $request->fresh()->status);

        (new PharmacyRequestReview)->approve($this->coordinator, $request->id, $other->id);
        $this->assertSame($other->id, Pharmacy::query()->sole()->currentAssignment->promoter_id);
    }

    /** RF-AB: aprobar una baja desactiva la ficha sin borrar asignaciones ni historial. */
    public function test_approving_a_baja_deactivates_without_deleting_history(): void
    {
        $pharmacy = $this->assignedPharmacy();
        $planner = new SchedulePlanner;
        $schedule = $planner->create($this->promoter, 2026, 10);
        $planner->addActivity($this->promoter, $schedule->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-10-20', 'pharmacy_id' => $pharmacy->id]);
        $planner->addActivity($this->promoter, $schedule->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-10-02', 'pharmacy_id' => $pharmacy->id]);
        $planner->submit($this->promoter, $schedule->id);
        $request = $this->baja($pharmacy);

        Livewire::actingAs($this->coordinator)->test(Index::class)
            ->call('select', $request->id)
            ->assertSee('Hay 1 visita planificada')
            ->assertSee('Ana Promotora')
            ->call('approve')
            ->assertHasNoErrors()
            ->assertSee('Baja aprobada');

        $pharmacy->refresh();
        $this->assertFalse($pharmacy->is_active);
        $this->assertSame(1, $pharmacy->assignments()->count());
        $this->assertSame(2, PlannedActivity::query()->where('pharmacy_id', $pharmacy->id)->count());
        $this->assertSame(PharmacyRequest::APPROVED, $request->fresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'pharmacy_request.approved')->sole()->details['pending_planned_visits']);

        // La promotora deja de verla en su fichero activo.
        $this->assertFalse($this->promoter->assignedPharmacies()->where('pharmacies.is_active', true)->whereKey($pharmacy->id)->exists());
    }

    /** RF-AB: rechazar exige motivo y no toca el fichero. */
    public function test_rejection_requires_a_note_and_leaves_the_catalog_untouched(): void
    {
        $pharmacy = $this->assignedPharmacy();
        $baja = $this->baja($pharmacy);
        $alta = $this->alta();

        Livewire::actingAs($this->coordinator)->test(Index::class)
            ->call('select', $baja->id)
            ->call('reject')
            ->assertHasErrors(['reviewNote' => 'required'])
            ->set('reviewNote', 'corto')
            ->call('reject')
            ->assertHasErrors(['reviewNote' => 'min'])
            ->set('reviewNote', 'Sigue abierta según la última visita registrada.')
            ->call('reject')
            ->assertHasNoErrors();

        $this->assertTrue($pharmacy->fresh()->is_active);
        $baja->refresh();
        $this->assertSame(PharmacyRequest::REJECTED, $baja->status);
        $this->assertSame($this->coordinator->id, $baja->reviewed_by);
        $this->assertSame('Sigue abierta según la última visita registrada.', $baja->review_note);

        try {
            (new PharmacyRequestReview)->reject($this->coordinator, $alta->id, '   ');
            $this->fail('Un rechazo sin motivo no debería aceptarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reviewNote', $e->errors());
        }
        (new PharmacyRequestReview)->reject($this->coordinator, $alta->id, 'Ya existe en el fichero con otro nombre.');
        $this->assertSame(1, Pharmacy::count());
        $this->assertSame(2, AuditLog::query()->where('action', 'pharmacy_request.rejected')->count());

        // La promotora ve el resultado y la nota en su historial.
        Livewire::actingAs($this->promoter)->test(PromoterRequests::class)
            ->assertSee('Rechazada')
            ->assertSee('Sigue abierta según la última visita registrada.');
    }

    public function test_a_request_cannot_be_resolved_twice(): void
    {
        $request = $this->alta();
        $review = new PharmacyRequestReview;
        $review->approve($this->coordinator, $request->id);

        foreach ([fn () => $review->approve($this->coordinator, $request->id), fn () => $review->reject($this->coordinator, $request->id, 'Motivo suficientemente largo')] as $attempt) {
            try {
                $attempt();
                $this->fail('Una solicitud resuelta no debería resolverse otra vez.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('review', $e->errors());
            }
        }

        $this->assertSame(1, Pharmacy::count());
        $this->assertSame(1, AuditLog::where('action', 'pharmacy_request.approved')->count());
        $this->assertSame(1, AuditLog::where('action', 'assignment.added')->count());
        $this->assertSame(2, AuditLog::count());
    }

    /** RNF-SEG: una promotora no puede resolver solicitudes (ni las propias). */
    public function test_promoters_cannot_review_requests(): void
    {
        $request = $this->alta();

        try {
            (new PharmacyRequestReview)->approve($this->promoter, $request->id);
            $this->fail('Una promotora no debería aprobar solicitudes.');
        } catch (AccessDeniedHttpException) {
        }

        $this->actingAs($this->promoter)->get('/coordinator/requests')->assertForbidden();
        Livewire::actingAs($this->promoter)->test(Index::class)->assertForbidden();
        $this->assertSame(PharmacyRequest::PENDING, $request->fresh()->status);
        $this->assertSame(0, Pharmacy::count());
    }

    public function test_list_filters_and_duplicate_warning(): void
    {
        Pharmacy::factory()->create(['name' => 'Farmacia Nueva Esperanza Sur', 'department' => 'Managua']);
        Pharmacy::factory()->create(['name' => 'Farmacia Nueva Esperanza', 'department' => 'León']);
        $alta = $this->alta();
        $baja = $this->baja($this->assignedPharmacy('Farmacia Del Carmen'));
        (new PharmacyRequestReview)->reject($this->coordinator, $baja->id, 'No corresponde la baja todavía.');

        Livewire::actingAs($this->coordinator)->test(Index::class)
            ->assertViewHas('requests', fn ($r) => $r->total() === 1)
            ->assertSee('Farmacia Nueva Esperanza')
            ->set('status', '')
            ->assertViewHas('requests', fn ($r) => $r->total() === 2)
            ->set('type', PharmacyRequest::TYPE_BAJA)
            ->assertViewHas('requests', fn ($r) => $r->total() === 1)
            ->set('type', '')
            ->set('search', 'Carmen')
            ->assertViewHas('requests', fn ($r) => $r->total() === 1 && $r->first()->id === $baja->id)
            ->set('search', '')
            ->call('select', $alta->id)
            ->assertSee('Posibles duplicados')
            ->assertViewHas('duplicates', fn ($d) => $d->pluck('name')->all() === ['Farmacia Nueva Esperanza Sur']);
    }
}
