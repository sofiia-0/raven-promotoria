<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Promoter\Pharmacies\Show;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyChange;
use App\Models\User;
use App\Services\PharmacyAssignmentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromoterPharmacyEditTest extends TestCase
{
    use RefreshDatabase;

    private function promoter(): User
    {
        return User::factory()->create([
            'role' => UserRole::PROMOTER,
            'is_active' => true,
        ]);
    }

    private function assigned(User $promoter): Pharmacy
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Farmacia Uno',
            'category' => 'AAA',
            'source_reference' => 'REV-9998',
            'source_responsible' => 'Fichero inicial',
            'is_active' => true,
        ]);

        PharmacyAssignment::create([
            'pharmacy_id' => $pharmacy->id,
            'promoter_id' => $promoter->id,
            'assigned_at' => now(),
        ]);

        return $pharmacy;
    }

    public function test_promoter_can_edit_own_pharmacy_and_sees_change_history(): void
    {
        $promoter = $this->promoter();
        $pharmacy = $this->assigned($promoter);

        $this->actingAs($promoter)
            ->get('/promoter/pharmacies/'.$pharmacy->id)
            ->assertOk();

        Livewire::actingAs($promoter)
            ->test(Show::class, ['pharmacyId' => $pharmacy->id])
            ->set('name', 'Farmacia Uno Sucursal 1')
            ->set('category', 'N/A')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Cambios guardados con historial');

        $this->assertSame(
            'Farmacia Uno Sucursal 1',
            $pharmacy->fresh()->name
        );

        $this->assertSame('N/A', $pharmacy->fresh()->category);
        $this->assertSame('REV-9998', $pharmacy->fresh()->source_reference);
        $this->assertSame('Fichero inicial', $pharmacy->fresh()->source_responsible);

        $this->assertSame(1, PharmacyChange::count());

        $this->assertSame(
            'Farmacia Uno',
            PharmacyChange::first()->before['name']
        );

        $this->assertSame(
            'Farmacia Uno Sucursal 1',
            PharmacyChange::first()->after['name']
        );
    }

    public function test_no_changes_does_not_create_a_log(): void
    {
        $promoter = $this->promoter();
        $pharmacy = $this->assigned($promoter);

        Livewire::actingAs($promoter)
            ->test(Show::class, ['pharmacyId' => $pharmacy->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, PharmacyChange::count());
    }

    public function test_other_promoter_cannot_open_or_modify_it(): void
    {
        $owner = $this->promoter();
        $other = $this->promoter();

        $pharmacy = $this->assigned($owner);

        $this->actingAs($other)
            ->get('/promoter/pharmacies/'.$pharmacy->id)
            ->assertNotFound();

        try {
            Livewire::actingAs($other)
                ->test(Show::class, ['pharmacyId' => $pharmacy->id]);

            $this->fail('Una promotora ajena no debería acceder a esta farmacia.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(Pharmacy::class, $exception->getModel());
        }

        $this->assertSame('Farmacia Uno', $pharmacy->fresh()->name);
        $this->assertSame(0, PharmacyChange::count());
    }

    public function test_transfer_revokes_edit_access_even_after_the_form_is_open(): void
    {
        $first = $this->promoter();
        $second = $this->promoter();

        $pharmacy = $this->assigned($first);

        $form = Livewire::actingAs($first)
            ->test(Show::class, ['pharmacyId' => $pharmacy->id]);

        (new PharmacyAssignmentService())
            ->reassign($pharmacy, $second);

        try {
            $form->set('name', 'Nombre no autorizado')
                ->call('save');

            $this->fail('No debería permitirse editar después de una reasignación.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(Pharmacy::class, $exception->getModel());
        }

        $this->assertSame('Farmacia Uno', $pharmacy->fresh()->name);
        $this->assertSame(0, PharmacyChange::count());
    }

    public function test_admin_cannot_access_promoter_edit_page(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/promoter/pharmacies/1')
            ->assertForbidden();
    }
}
