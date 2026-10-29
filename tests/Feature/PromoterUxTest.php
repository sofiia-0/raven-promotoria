<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Promoter\Pharmacies\Show;
use App\Livewire\Promoter\PharmacyRequests\Index;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyChange;
use App\Models\PharmacyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromoterUxTest extends TestCase
{
    use RefreshDatabase;

    private function promoter(): User
    {
        return User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => true]);
    }

    private function assigned(User $actor, array $values = []): Pharmacy
    {
        $pharmacy = Pharmacy::create(array_merge(['name' => 'Establecimiento de prueba', 'is_active' => true], $values));
        PharmacyAssignment::create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $actor->id, 'assigned_at' => now()]);

        return $pharmacy;
    }

    public function test_requests_start_with_actions_and_history_without_forms(): void
    {
        Livewire::actingAs($this->promoter())->test(Index::class)
            ->assertSet('activeForm', null)
            ->assertSee('Solicitar alta')->assertSee('Solicitar baja')
            ->assertSee('Historial de solicitudes')
            ->assertDontSeeHtml('wire:submit="submitAlta"')
            ->assertDontSeeHtml('wire:submit="submitBaja"');
    }

    public function test_forms_are_exclusive_and_switching_preserves_drafts(): void
    {
        Livewire::actingAs($this->promoter())->test(Index::class)
            ->call('openForm', 'alta')->set('altaName', 'Borrador sin enviar')
            ->assertSeeHtml('wire:submit="submitAlta"')->assertDontSeeHtml('wire:submit="submitBaja"')
            ->call('openForm', 'baja')
            ->assertSeeHtml('wire:submit="submitBaja"')->assertDontSeeHtml('wire:submit="submitAlta"')
            ->call('openForm', 'alta')->assertSet('altaName', 'Borrador sin enviar')
            ->call('closeForm')->assertSet('activeForm', null);

        $this->assertSame(0, PharmacyRequest::count());
    }

    public function test_valid_pharmacy_link_opens_baja_but_foreign_link_does_not(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor);
        $foreign = $this->assigned($this->promoter());

        Livewire::withQueryParams(['pharmacy' => $mine->id])->actingAs($actor)->test(Index::class)
            ->assertSet('activeForm', 'baja')->assertSet('pharmacyId', (string) $mine->id);
        Livewire::withQueryParams(['pharmacy' => $foreign->id])->actingAs($actor)->test(Index::class)
            ->assertSet('activeForm', null)->assertSet('pharmacyId', '');
    }

    public function test_baja_identifies_same_name_pharmacies_by_location(): void
    {
        $actor = $this->promoter();
        $first = $this->assigned($actor, ['name' => 'Nombre repetido', 'department' => 'Departamento Uno', 'city' => 'Municipio Uno', 'address' => 'Dirección conocida']);
        $this->assigned($actor, ['name' => 'Nombre repetido', 'department' => 'Departamento Dos', 'city' => 'Municipio Dos']);

        Livewire::actingAs($actor)->test(Index::class)->call('openForm', 'baja')
            ->assertSee('Municipio Uno')->assertSee('Municipio Dos')
            ->set('pharmacyId', (string) $first->id)->assertSee('Dirección conocida');
    }

    public function test_form_options_only_use_current_active_assignments(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor, ['department' => 'Propio', 'city' => 'Local', 'client_type' => 'Tipo propio', 'category' => 'Histórica']);
        $this->assigned($this->promoter(), ['department' => 'Ajeno', 'city' => 'Privado', 'client_type' => 'Tipo ajeno', 'category' => 'Ajena']);
        $this->assigned($actor, ['department' => 'Inactivo', 'is_active' => false]);
        $ended = $this->assigned($actor, ['department' => 'Terminado']);
        $ended->assignments()->update(['ended_at' => now()]);

        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $mine->id])
            ->assertViewHas('catalogOptions', fn ($options) => $options['department']->contains('Propio') && $options['department']->contains('Boaco') && $options['city']->all() === ['Local'] && $options['client_type']->all() === ['Tipo propio'])
            ->assertDontSee('Ajeno')->assertDontSee('Privado')->assertDontSee('Inactivo')->assertDontSee('Terminado');
    }

    public function test_department_changes_clear_municipality_without_saving(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor, ['department' => 'Managua', 'city' => 'Managua']);
        $this->assigned($actor, ['department' => 'Masaya', 'city' => 'Masaya']);

        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $mine->id])
            ->assertSet('city', 'Managua')->set('department', 'Masaya')->assertSet('city', '')
            ->assertViewHas('catalogOptions', fn ($options) => $options['city']->contains('Masaya') && ! $options['city']->contains('Managua'));

        $this->assertSame('Managua', $mine->fresh()->department);
        $this->assertSame('Managua', $mine->fresh()->city);
        $this->assertSame(0, PharmacyChange::count());
    }

    public function test_edit_rejects_municipality_from_another_department(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor, ['department' => 'Managua', 'city' => 'Managua']);
        $this->assigned($actor, ['department' => 'Masaya', 'city' => 'Masaya']);
        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $mine->id])
            ->set('department', 'Masaya')->set('city', 'Managua')->call('save')->assertHasErrors(['city']);
        $this->assertSame('Managua', $mine->fresh()->department);
        $this->assertSame(0, PharmacyChange::count());
    }

    public function test_unrelated_edits_preserve_historical_values_and_assignments(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor, ['department' => 'Ubicación histórica', 'city' => 'Localidad antigua', 'category' => 'N/A', 'client_type' => 'Tipo histórico']);
        $assignmentId = $mine->currentAssignment->id;

        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $mine->id])
            ->assertSet('city', 'Localidad antigua')->set('phone', '12345678')->call('save')->assertHasNoErrors();

        $fresh = $mine->fresh();
        $this->assertSame('N/A', $fresh->category);
        $this->assertSame('Tipo histórico', $fresh->client_type);
        $this->assertSame('Localidad antigua', $fresh->city);
        $this->assertSame($assignmentId, $fresh->currentAssignment->id);
        $this->assertSame(['phone'], array_keys(PharmacyChange::first()->after));
    }

    public function test_alta_municipalities_depend_on_department_and_are_validated(): void
    {
        $actor = $this->promoter();
        $this->assigned($actor, ['department' => 'Managua', 'city' => 'Managua']);
        $this->assigned($actor, ['department' => 'Masaya', 'city' => 'Masaya']);
        Livewire::actingAs($actor)->test(Index::class)->call('openForm', 'alta')
            ->set('altaDepartment', 'Managua')->set('altaCity', 'Managua')
            ->set('altaDepartment', 'Masaya')->assertSet('altaCity', '')
            ->assertViewHas('catalogOptions', fn ($options) => $options['city']->contains('Masaya') && ! $options['city']->contains('Managua'))
            ->set('altaCity', 'Managua')->set('altaName', 'Nueva ficha')
            ->set('altaAddress', 'Dirección de prueba.')
            ->set('altaReason', 'Solicitud de prueba válida.')->call('submitAlta')->assertHasErrors(['altaCity']);
        $this->assertSame(0, PharmacyRequest::count());
    }

    public function test_national_options_are_available_without_assigned_pharmacies(): void
    {
        Livewire::actingAs($this->promoter())->test(Index::class)->call('openForm', 'alta')
            ->assertSee('Boaco')->assertSee('Región Autónoma de la Costa Caribe Norte (RACCN)')
            ->set('altaDepartment', 'Boaco')
            ->set('altaCity', 'Camoapa')
            ->set('altaAddress', 'Dirección completa de prueba.')
            ->set('altaName', 'Propuesta de establecimiento')
            ->set('altaReason', 'Debe revisarse antes de incorporarlo.')
            ->call('submitAlta')->assertHasNoErrors()->assertSet('activeForm', null);
        $this->assertSame(0, Pharmacy::count());
        $this->assertSame('pending', PharmacyRequest::first()->status);
    }

    public function test_validation_is_formal_and_associated_with_the_field(): void
    {
        Livewire::actingAs($this->promoter())->test(Index::class)->call('openForm', 'alta')
            ->call('submitAlta')->assertHasErrors(['altaName', 'altaDepartment', 'altaReason'])
            ->assertSee('Complete el campo nombre.')
            ->assertSeeHtml('aria-invalid="true"')
            ->assertSeeHtml('aria-describedby="feedback-altaName"');
    }

    public function test_denied_promoter_access_uses_formal_language_without_changing_permissions(): void
    {
        foreach ([UserRole::ADMIN, UserRole::COORDINATOR] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->getJson('/promoter')->assertForbidden()
                ->assertJsonPath('message', 'No tiene permisos para acceder a esta sección.');
        }
        $this->actingAs(User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => false]))
            ->getJson('/promoter')->assertForbidden()
            ->assertJsonPath('message', 'Esta cuenta se encuentra desactivada.');
    }
}
