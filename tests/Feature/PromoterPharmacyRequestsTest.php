<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Promoter\PharmacyRequests\Index;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Services\PharmacyAssignmentService;
use App\Services\PharmacyRequestService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromoterPharmacyRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function promoter(): User
    {
        return User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => true]);
    }

    private function pharmacy(User $promoter, string $name = 'Farmacia Asignada'): Pharmacy
    {
        $p = Pharmacy::create(['name' => $name, 'is_active' => true]);
        PharmacyAssignment::create([
            'pharmacy_id' => $p->id,
            'promoter_id' => $promoter->id,
            'assigned_at' => now(),
        ]);

        return $p;
    }

    public function test_alta_creates_only_pending_request_not_pharmacy(): void
    {
        $promoter = $this->promoter();
        $this->actingAs($promoter)->get('/promoter/requests')->assertOk();

        Livewire::actingAs($promoter)->test(Index::class)
            ->set('altaName', 'Nueva Farmacia DEMO')
            ->set('altaDepartment', 'Managua')
            ->set('altaCity', 'Managua')
            ->set('altaAddress', 'Dirección de prueba suficiente.')
            ->set('altaReason', 'Nuevo establecimiento que necesita cobertura.')
            ->call('submitAlta')
            ->assertHasNoErrors()
            ->assertSee('Solicitud de alta registrada');

        $this->assertSame(0, Pharmacy::count());
        $this->assertSame(1, PharmacyRequest::count());
        $request = PharmacyRequest::firstOrFail();
        $this->assertSame(PharmacyRequest::TYPE_ALTA, $request->type);
        $this->assertSame(PharmacyRequest::PENDING, $request->status);
        $this->assertNull($request->pharmacy_id);
        $this->assertSame('Nueva Farmacia DEMO', $request->data['name']);
        $this->assertSame($promoter->id, $request->promoter_id);
    }

    public function test_baja_remains_pending_and_does_not_disable_pharmacy(): void
    {
        $promoter = $this->promoter();
        $p = $this->pharmacy($promoter);

        Livewire::actingAs($promoter)->test(Index::class)
            ->set('pharmacyId', (string) $p->id)
            ->set('bajaReason', 'Este establecimiento cerró sus operaciones.')
            ->call('submitBaja')
            ->assertHasNoErrors()
            ->assertSee('Solicitud de baja registrada');

        $request = PharmacyRequest::firstOrFail();
        $this->assertSame(PharmacyRequest::TYPE_BAJA, $request->type);
        $this->assertSame(PharmacyRequest::PENDING, $request->status);
        $this->assertSame($p->id, $request->pharmacy_id);
        $this->assertTrue($p->fresh()->is_active);
        $this->assertNull($p->currentAssignment->ended_at);
    }

    public function test_promoter_cannot_request_baja_for_another_promoters_pharmacy(): void
    {
        $owner = $this->promoter();
        $other = $this->promoter();
        $p = $this->pharmacy($owner);

        try {
            Livewire::actingAs($other)->test(Index::class)
                ->set('pharmacyId', (string) $p->id)
                ->set('bajaReason', 'Solicitud de baja sin autorización.')
                ->call('submitBaja');
            $this->fail('Una promotora no puede solicitar bajas de farmacias ajenas.');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(Pharmacy::class, $e->getModel());
        }
        $this->assertSame(0, PharmacyRequest::count());
    }

    public function test_duplicate_pending_baja_is_rejected(): void
    {
        $promoter = $this->promoter();
        $p = $this->pharmacy($promoter);
        $service = new PharmacyRequestService;
        $service->submitBaja($promoter, $p->id, 'Ya no se atiende este establecimiento.');

        Livewire::actingAs($promoter)->test(Index::class)
            ->set('pharmacyId', (string) $p->id)
            ->set('bajaReason', 'Repito esta solicitud por equivocación.')
            ->call('submitBaja')
            ->assertHasErrors(['pharmacyId']);

        $this->assertSame(1, PharmacyRequest::count());
    }

    public function test_access_is_rejected_after_assignment_transfer(): void
    {
        $first = $this->promoter();
        $second = $this->promoter();
        $p = $this->pharmacy($first);
        $form = Livewire::actingAs($first)->test(Index::class);
        (new PharmacyAssignmentService)->reassign($p, $second);

        try {
            $form->set('pharmacyId', (string) $p->id)
                ->set('bajaReason', 'Antes estaba asignada a mi fichero.')
                ->call('submitBaja');
            $this->fail('No se permite solicitar baja tras una reasignación.');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(Pharmacy::class, $e->getModel());
        }
        $this->assertSame(0, PharmacyRequest::count());
    }

    public function test_each_promoter_sees_only_own_request_history(): void
    {
        $mine = $this->promoter();
        $other = $this->promoter();
        $service = new PharmacyRequestService;
        $service->submitAlta($mine, [
            'name' => 'Alta Propia DEMO',
            'department' => 'Managua', 'city' => 'Managua', 'address' => 'Dirección de prueba.',
            'reason' => 'Necesitamos este establecimiento nuevo.',
        ]);
        $service->submitAlta($other, [
            'name' => 'Alta Ajena SECRETA',
            'department' => 'León', 'city' => 'León', 'address' => 'Dirección de prueba.',
            'reason' => 'Quiero añadir este establecimiento nuevo.',
        ]);

        Livewire::actingAs($mine)->test(Index::class)
            ->assertSee('Alta Propia DEMO')
            ->assertDontSee('Alta Ajena SECRETA');
    }

    public function test_invalid_alta_does_not_create_request(): void
    {
        $promoter = $this->promoter();
        Livewire::actingAs($promoter)->test(Index::class)
            ->set('altaName', '')
            ->set('altaDepartment', '')
            ->set('altaReason', 'Corto')
            ->call('submitAlta')
            ->assertHasErrors(['altaName', 'altaDepartment', 'altaReason']);
        $this->assertSame(0, PharmacyRequest::count());
    }

    public function test_admin_and_inactive_promoter_cannot_access_requests(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $inactive = User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => false]);
        $this->actingAs($admin)->get('/promoter/requests')->assertForbidden();
        $this->actingAs($inactive)->get('/promoter/requests')->assertForbidden();
        Livewire::actingAs($admin)->test(Index::class)->assertForbidden();
    }
}
