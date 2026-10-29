<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Promoter\Pharmacies\Show;
use App\Livewire\Promoter\PharmacyRequests\Index as Requests;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyChange;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Services\PharmacyEditor;
use App\Services\PharmacyRequestService;
use App\Support\PromoterCatalogOptions as Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromoterCommercialSelectorsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => true]);
    }

    private function assigned(User $actor, array $attributes = []): Pharmacy
    {
        $pharmacy = Pharmacy::create(array_merge(['name' => 'Establecimiento de prueba', 'department' => 'Managua', 'city' => 'Managua', 'client_type' => 'Clínica de prueba', 'category' => 'Élite de prueba', 'is_active' => true], $attributes));
        PharmacyAssignment::create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $actor->id, 'assigned_at' => now()]);

        return $pharmacy;
    }

    private function alta(User $actor)
    {
        return Livewire::actingAs($actor)->test(Requests::class)->call('openForm', 'alta')
            ->set('altaName', 'Nuevo establecimiento de prueba')->set('altaDepartment', 'Boaco')
            ->set('altaCity', 'Camoapa')->set('altaAddress', 'Dirección detallada de prueba.')
            ->set('altaReason', 'Motivo de prueba para revisión.');
    }

    public function test_options_deduplicate_case_accents_and_spacing_without_touching_records(): void
    {
        $actor = $this->actor();
        $first = $this->assigned($actor);
        $second = $this->assigned($actor, ['client_type' => '  CLINICA   de prueba ', 'category' => 'ELITE DE PRUEBA']);
        $this->assigned($this->actor(), ['client_type' => 'Privado ajeno', 'category' => 'Ajena']);
        $this->assertCount(1, Catalog::values($actor, 'client_type'));
        $this->assertCount(1, Catalog::values($actor, 'category'));
        $this->assertSame('  CLINICA   de prueba ', $second->fresh()->client_type);
        $this->assertSame('Clínica de prueba', $first->fresh()->client_type);
        $this->assertFalse(Catalog::values($actor, 'client_type')->contains('Privado ajeno'));
    }

    public function test_both_forms_render_searchable_commercial_controls(): void
    {
        $actor = $this->actor();
        $pharmacy = $this->assigned($actor);
        $this->alta($actor)->assertSeeHtml('id="geo-altaClientType"')->assertSeeHtml('id="geo-altaCategory"');
        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $pharmacy->id])
            ->assertSeeHtml('id="geo-client_type"')->assertSeeHtml('id="geo-category"');
    }

    public function test_known_selection_is_saved_without_catalog_proposals(): void
    {
        $actor = $this->actor();
        $this->assigned($actor);
        $this->alta($actor)->set('altaClientType', 'Clínica de prueba')->set('altaCategory', 'Élite de prueba')->call('submitAlta')->assertHasNoErrors();
        $data = PharmacyRequest::first()->data;
        $this->assertSame('Clínica de prueba', $data['client_type']);
        $this->assertArrayNotHasKey('catalog_proposals', $data);
        $this->assertSame(1, Pharmacy::count());
    }

    public function test_custom_values_are_pending_proposals_not_new_catalog_records(): void
    {
        $actor = $this->actor();
        $this->assigned($actor);
        $this->alta($actor)->set('altaClientType', Catalog::OTHER)->set('altaClientTypeOther', '  Tipo   propuesto  ')
            ->set('altaCategory', Catalog::OTHER)->set('altaCategoryOther', 'Categoría propuesta')
            ->call('submitAlta')->assertHasNoErrors()->assertSet('altaClientTypeOther', '')->assertSet('altaCategoryOther', '');
        $request = PharmacyRequest::first();
        $this->assertSame('pending', $request->status);
        $this->assertEqualsCanonicalizing(['client_type' => 'Tipo propuesto', 'category' => 'Categoría propuesta'], $request->data['catalog_proposals']);
        $this->assertSame('Tipo propuesto', $request->data['client_type']);
        $this->assertFalse(Catalog::values($actor, 'client_type')->contains('Tipo propuesto'));
        $this->assertSame(1, Pharmacy::count());
        Livewire::actingAs($actor)->test(Requests::class)->assertSee('Tipo propuesto')->assertSee('Categoría propuesta');
    }

    public function test_other_matching_known_values_reuses_existing_spelling(): void
    {
        $actor = $this->actor();
        $this->assigned($actor);
        $this->alta($actor)->set('altaClientType', Catalog::OTHER)->set('altaClientTypeOther', ' CLINICA  DE PRUEBA ')
            ->set('altaCategory', Catalog::OTHER)->set('altaCategoryOther', 'elite de PRUEBA')->call('submitAlta')->assertHasNoErrors();
        $data = PharmacyRequest::first()->data;
        $this->assertSame('Clínica de prueba', $data['client_type']);
        $this->assertSame('Élite de prueba', $data['category']);
        $this->assertArrayNotHasKey('catalog_proposals', $data);
    }

    public function test_other_requires_nonblank_value_and_keeps_existing_length_limits(): void
    {
        $actor = $this->actor();
        $this->alta($actor)->set('altaClientType', Catalog::OTHER)->set('altaClientTypeOther', '   ')
            ->set('altaCategory', Catalog::OTHER)->set('altaCategoryOther', str_repeat('x', 21))->call('submitAlta')
            ->assertHasErrors(['altaClientTypeOther' => 'required', 'altaCategoryOther' => 'max']);
        $this->assertSame(0, PharmacyRequest::count());
    }

    public function test_unselected_text_and_reserved_or_control_values_cannot_be_submitted(): void
    {
        $actor = $this->actor();
        $form = $this->alta($actor)->set('altaClientType', 'Not selected')->call('submitAlta')->assertHasErrors('altaClientType');
        $form->set('altaClientType', Catalog::OTHER)->set('altaClientTypeOther', "Bad\x01Value")->call('submitAlta')->assertHasErrors(['altaClientTypeOther' => 'regex']);
        $form->set('altaClientTypeOther', 'Otro (especificar)')->call('submitAlta')->assertHasErrors(['altaClientTypeOther' => 'not_in']);
        $this->assertSame(0, PharmacyRequest::count());
    }

    public function test_hidden_custom_values_are_ignored_and_optional_selections_can_be_cleared(): void
    {
        $actor = $this->actor();
        $this->assigned($actor);
        $this->alta($actor)->set('altaClientType', Catalog::OTHER)->set('altaClientTypeOther', 'Stale proposal')
            ->set('altaClientType', 'Clínica de prueba')->assertSet('altaClientTypeOther', '')
            ->set('altaClientType', '')->set('altaCategoryOther', str_repeat('x', 100))->call('submitAlta')->assertHasNoErrors();
        $data = PharmacyRequest::first()->data;
        $this->assertNull($data['client_type']);
        $this->assertNull($data['category']);
        $this->assertArrayNotHasKey('catalog_proposals', $data);
    }

    public function test_historical_values_remain_available_and_unchanged_while_editing_phone(): void
    {
        $actor = $this->actor();
        $old = $this->assigned($actor, ['client_type' => 'Tipo histórico', 'category' => 'Categoría antigua', 'address' => null]);
        $options = Catalog::withHistorical(['client_type' => collect(['Habitual']), 'category' => collect(['Habitual'])], $old, 'Managua');
        $this->assertTrue($options['client_type']->contains('Tipo histórico'));
        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $old->id])->set('phone', '0000-0000')->call('save')->assertHasNoErrors();
        $this->assertSame('Tipo histórico', $old->fresh()->client_type);
        $this->assertSame('Categoría antigua', $old->fresh()->category);
        $this->assertNull($old->fresh()->address);
        $this->assertSame(['phone' => '0000-0000'], PharmacyChange::first()->after);
    }

    public function test_equivalent_edit_does_not_rewrite_historical_spelling_or_create_history(): void
    {
        $actor = $this->actor();
        $old = $this->assigned($actor);
        $this->assertFalse(app(PharmacyEditor::class)->update($old->id, $actor, ['client_type' => 'CLINICA DE PRUEBA', 'category' => 'elite de prueba']));
        $this->assertSame('Clínica de prueba', $old->fresh()->client_type);
        $this->assertSame(0, PharmacyChange::count());
    }

    public function test_edit_can_select_available_value_but_cannot_create_an_unknown_one(): void
    {
        $actor = $this->actor();
        $old = $this->assigned($actor);
        $this->assigned($actor, ['category' => 'Otra de prueba']);
        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $old->id])->set('category', 'OTRA DE PRUEBA')->call('save')->assertHasNoErrors();
        $this->assertSame('Otra de prueba', $old->fresh()->category);
        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $old->id])->set('category', 'Inventada')->call('save')->assertHasErrors('category');
        $this->assertSame('Otra de prueba', $old->fresh()->category);
    }

    public function test_service_marks_custom_proposal_and_ignores_forged_metadata(): void
    {
        $actor = $this->actor();
        $request = app(PharmacyRequestService::class)->submitAlta($actor, ['name' => 'Prueba', 'department' => 'Boaco', 'city' => 'Camoapa', 'address' => 'Dirección de prueba.', 'reason' => 'Solicitud de prueba.', 'client_type' => 'Tipo propuesto', 'catalog_proposals' => ['category' => 'Falso'], 'status' => 'approved']);
        $this->assertSame(['client_type' => 'Tipo propuesto'], $request->data['catalog_proposals']);
        $this->assertSame('pending', $request->status);
        $this->assertSame(0, Pharmacy::count());
    }

    public function test_ended_assignments_and_other_users_do_not_supply_options(): void
    {
        $actor = $this->actor();
        $old = $this->assigned($actor);
        $old->assignments()->update(['ended_at' => now()]);
        $this->assertCount(0, Catalog::values($actor, 'client_type'));
        $this->actingAs($actor)->get('/promoter/pharmacies/'.$old->id)->assertNotFound();
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->actingAs($admin)->get('/promoter/requests')->assertForbidden();
    }
}
