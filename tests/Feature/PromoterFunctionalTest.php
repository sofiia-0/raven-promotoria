<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Promoter\Pharmacies\Index as Pharmacies;
use App\Livewire\Promoter\Pharmacies\Show;
use App\Livewire\Promoter\PharmacyRequests\Index as Requests;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyChange;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Services\PharmacyEditor;
use App\Services\PharmacyRequestService;
use App\Support\EstablishmentPresentation;
use App\Support\EstablishmentSearch;
use App\Support\NicaraguaGeography;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\MySqlConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PromoterFunctionalTest extends TestCase
{
    use RefreshDatabase;

    private function promoter(): User
    {
        return User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => true]);
    }

    private function assigned(User $actor, array $values = []): Pharmacy
    {
        $pharmacy = Pharmacy::create(array_merge([
            'name' => 'Acuña · Sucursal Central', 'department' => 'Managua', 'city' => 'Ticuantepe',
            'neighborhood' => 'Zona de prueba', 'address' => 'De la plaza, dos calles al norte.',
            'is_active' => true,
        ], $values));
        PharmacyAssignment::create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $actor->id, 'assigned_at' => now()]);

        return $pharmacy;
    }

    private function validAlta(): array
    {
        return ['name' => 'Acuña', 'department' => 'Boaco', 'city' => 'Camoapa',
            'address' => 'Del parque, una calle al este.', 'reason' => 'Solicitud de establecimiento de prueba.'];
    }

    public function test_national_geography_has_exactly_the_provided_153_pairs_and_17_units(): void
    {
        $catalog = NicaraguaGeography::catalog();
        $this->assertCount(17, $catalog['departments']);
        $this->assertSame(153, array_sum(array_map('count', $catalog['departments'])));
        $this->assertCount(2, array_filter(array_keys($catalog['departments']), fn ($name) => str_contains($name, 'Región Autónoma')));
        $this->assertSame('7ee62a8e00de328df6429a1626cedaf1d97aea9d98d69766ec5f20fd5a766b1b', $catalog['source_sha256']);
        $pairs = [];
        foreach ($catalog['departments'] as $department => $cities) {
            foreach ($cities as $city) {
                $pairs[] = $department.'|'.$city;
            }
        }
        $this->assertCount(153, array_unique($pairs));
        $this->assertTrue(NicaraguaGeography::municipalities('Matagalpa')->contains('El Tuma - La Dalia'));
    }

    public function test_alta_offers_all_regions_without_any_assigned_establishments(): void
    {
        $actor = $this->promoter();
        $north = 'Región Autónoma de la Costa Caribe Norte (RACCN)';
        Livewire::actingAs($actor)->test(Requests::class)->call('openForm', 'alta')
            ->assertViewHas('catalogOptions', fn ($options) => $options['department']->count() === 17)
            ->set('altaDepartment', $north)
            ->assertViewHas('catalogOptions', fn ($options) => $options['city']->count() === 8 && $options['city']->contains('Puerto Cabezas'))
            ->set('altaCity', 'Puerto Cabezas')->set('altaName', 'Establecimiento nuevo')
            ->set('altaAddress', 'Dirección de prueba en la zona central.')
            ->set('altaReason', 'Solicitud de cobertura de prueba.')->call('submitAlta')->assertHasNoErrors();
        $this->assertSame($north, PharmacyRequest::first()->data['department']);
        $this->assertSame(0, Pharmacy::count());
    }

    public function test_alta_location_has_three_controls_and_persistent_address_hints(): void
    {
        $html = Livewire::actingAs($this->promoter())->test(Requests::class)->call('openForm', 'alta')->html();
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('aria-controls="geo-altaDepartment-list"', $html);
        $this->assertStringContainsString('Indique zona, barrio, calle, número, puntos de referencia y cómo llegar, según corresponda.', $html);
        $this->assertStringContainsString('No es necesario repetir el departamento ni el municipio.', $html);
        $this->assertStringNotContainsString('model="altaNeighborhood"', $html);
        $this->assertStringNotContainsString('Barrio / zona', $html);
    }

    public function test_service_requires_address_and_municipality_for_new_altas(): void
    {
        $actor = $this->promoter();
        try {
            app(PharmacyRequestService::class)->submitAlta($actor, array_merge($this->validAlta(), ['address' => '   ', 'city' => '']));
            $this->fail('Expected required location fields.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('address', $error->errors());
            $this->assertArrayHasKey('city', $error->errors());
        }
        $this->assertSame(0, PharmacyRequest::count());
    }

    public function test_service_rejects_wrong_department_municipality_pair(): void
    {
        try {
            app(PharmacyRequestService::class)->submitAlta($this->promoter(), array_merge($this->validAlta(), ['city' => 'Managua']));
            $this->fail('Expected national pair validation.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('city', $error->errors());
        }
        $this->assertSame(0, Pharmacy::count());
        $this->assertSame(0, PharmacyRequest::count());
    }

    public function test_new_alta_stores_plain_name_and_separate_location_ignoring_hidden_old_fields(): void
    {
        $request = app(PharmacyRequestService::class)->submitAlta($this->promoter(), array_merge($this->validAlta(), [
            'neighborhood' => 'No debe entrar como campo del alta', 'pharmacy_id' => 999, 'is_active' => true,
        ]));
        $this->assertSame('Acuña', $request->data['name']);
        $this->assertSame('Boaco', $request->data['department']);
        $this->assertSame('Camoapa', $request->data['city']);
        $this->assertArrayNotHasKey('neighborhood', $request->data);
        $this->assertNull($request->pharmacy_id);
        $this->assertSame('pending', $request->status);
        $this->assertSame(0, Pharmacy::count());
    }

    public function test_new_alta_rejects_middle_dot_in_the_name(): void
    {
        try {
            app(PharmacyRequestService::class)->submitAlta($this->promoter(), array_merge($this->validAlta(), ['name' => 'Acuña · Boaco']));
            $this->fail('The separator belongs to the presentation.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('name', $error->errors());
        }
    }

    public function test_historical_phone_edit_does_not_require_new_address_or_location(): void
    {
        $actor = $this->promoter();
        $pharmacy = $this->assigned($actor, ['city' => 'Ciudad Doral', 'address' => null, 'name' => 'Acuña · Sucursal histórica']);
        $before = $pharmacy->getAttributes();
        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $pharmacy->id])
            ->set('phone', '12345678')->call('save')->assertHasNoErrors();
        $fresh = $pharmacy->fresh();
        foreach (['name', 'department', 'city', 'neighborhood', 'address'] as $field) {
            $this->assertSame($before[$field], $fresh->{$field});
        }
        $this->assertSame(['phone'], array_keys(PharmacyChange::first()->after));
        $this->assertSame(1, $fresh->assignments()->whereNull('ended_at')->count());
    }

    public function test_changed_location_is_nationally_validated_even_through_editor_service(): void
    {
        $actor = $this->promoter();
        $pharmacy = $this->assigned($actor, ['city' => 'Ciudad Doral', 'address' => null]);
        try {
            app(PharmacyEditor::class)->update($pharmacy->id, $actor, ['department' => 'Masaya', 'city' => 'Managua']);
            $this->fail('Expected changed-location validation.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('city', $error->errors());
        }
        $this->assertNull($pharmacy->fresh()->address);
        $this->assertSame(0, PharmacyChange::count());
    }

    public function test_search_terms_cross_fields_without_case_accents_or_comma_sensitivity(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor);
        $this->assigned($actor, ['name' => 'Acuña', 'department' => 'León', 'city' => 'León']);
        foreach (['ACU, MANAGUA', 'Acu Managua', '  acú, , manágua  ', "acu\u{0301} MANAGUA"] as $search) {
            $this->assertSame([$mine->id], EstablishmentSearch::forPromoter($actor, $search)->pluck('id')->all());
        }
        Livewire::actingAs($actor)->test(Pharmacies::class)->set('search', 'Acu, Managua')
            ->assertViewHas('pharmacies', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $mine->id);
    }

    public function test_search_covers_neighborhood_address_historical_branch_and_reference(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor, ['source_reference' => 'REV-TEST-1']);
        foreach (['acu zona', 'acu plaza', 'sucursal ticuante', 'REV-TEST-1 Managua'] as $search) {
            $this->assertSame([$mine->id], EstablishmentSearch::forPromoter($actor, $search)->pluck('id')->all());
        }
    }

    public function test_every_term_must_match_the_same_establishment(): void
    {
        $actor = $this->promoter();
        $this->assigned($actor, ['name' => 'Acuña', 'department' => 'León', 'city' => 'León', 'address' => 'Dirección norte']);
        $this->assigned($actor, ['name' => 'Otra farmacia', 'department' => 'Managua', 'address' => 'Dirección sur']);
        $this->assertSame(0, EstablishmentSearch::forPromoter($actor, 'acu managua')->count());
        $this->assertSame(0, EstablishmentSearch::forPromoter($actor, 'acu leon uno dos tres cuatro cinco seis siete ocho nueve diez inexistente')->count());
    }

    public function test_like_wildcards_and_sql_injection_are_treated_as_literal_input(): void
    {
        $actor = $this->promoter();
        $special = $this->assigned($actor, ['name' => 'Oferta 100%_!']);
        $this->assigned($actor, ['name' => 'Oferta 100 normal']);
        $this->assertSame([$special->id], EstablishmentSearch::forPromoter($actor, '100%_!')->pluck('id')->all());
        $this->assertSame(0, EstablishmentSearch::forPromoter($actor, "' OR 1=1 --")->count());
    }

    public function test_shared_assignments_remain_visible_to_each_responsible_only(): void
    {
        $first = $this->promoter();
        $second = $this->promoter();
        $third = $this->promoter();
        $shared = $this->assigned($first);
        PharmacyAssignment::create(['pharmacy_id' => $shared->id, 'promoter_id' => $second->id, 'assigned_at' => now()]);
        $this->assertSame(1, EstablishmentSearch::forPromoter($first, 'acu managua')->count());
        $this->assertSame(1, EstablishmentSearch::forPromoter($second, 'acu managua')->count());
        $this->assertSame(0, EstablishmentSearch::forPromoter($third, 'acu managua')->count());
        $shared->assignments()->where('promoter_id', $first->id)->update(['ended_at' => now()]);
        $this->assertSame(0, EstablishmentSearch::forPromoter($first)->count());
        $this->assertSame(1, EstablishmentSearch::forPromoter($second)->count());
    }

    public function test_search_excludes_ended_inactive_and_other_promoters_records(): void
    {
        $actor = $this->promoter();
        $this->assigned($this->promoter());
        $this->assigned($actor, ['is_active' => false]);
        $ended = $this->assigned($actor);
        $ended->assignments()->update(['ended_at' => now()]);
        $this->assertSame(0, EstablishmentSearch::forPromoter($actor, 'acu managua')->count());
        Livewire::actingAs($actor)->test(Requests::class)->call('openForm', 'baja')->set('bajaSearch', 'acu managua')
            ->assertViewHas('pharmacies', fn ($rows) => $rows->isEmpty())->assertViewHas('selectedPharmacy', null);
    }

    public function test_category_and_department_filters_still_apply_to_multi_term_search(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor, ['category' => 'AAA']);
        $this->assigned($actor, ['category' => 'AA']);
        Livewire::actingAs($actor)->test(Pharmacies::class)->set('search', 'acu managua')->set('category', 'AAA')->set('department', 'Managua')
            ->assertViewHas('pharmacies', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $mine->id);
    }

    public function test_baja_selection_survives_filtering_and_does_not_disable_pharmacy(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor);
        Livewire::actingAs($actor)->test(Requests::class)->call('openForm', 'baja')
            ->set('bajaSearch', 'ACU, MANAGUA')->assertViewHas('pharmacies', fn ($rows) => $rows->first()->id === $mine->id)
            ->set('pharmacyId', (string) $mine->id)->set('bajaSearch', 'Sin coincidencias')
            ->assertViewHas('pharmacies', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('selectedPharmacy', fn ($row) => $row?->id === $mine->id)
            ->set('bajaReason', 'Solicitud de prueba para revisión.')->call('submitBaja')->assertHasNoErrors();
        $this->assertTrue($mine->fresh()->is_active);
        $this->assertSame('pending', PharmacyRequest::first()->status);
    }

    public function test_baja_history_does_not_expand_access_after_assignment_ends(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor, ['name' => 'Nombre privado posterior']);
        app(PharmacyRequestService::class)->submitBaja($actor, $mine->id, 'Solicitud anterior de prueba.');
        $mine->assignments()->update(['ended_at' => now()]);
        Livewire::actingAs($actor)->test(Requests::class)->assertSee('Solicitud anterior de prueba.')
            ->assertDontSee('Nombre privado posterior')->assertSee('Establecimiento sin asignación vigente');
    }

    public function test_projection_uses_department_and_keeps_branch_secondary_without_writes(): void
    {
        $actor = $this->promoter();
        $mine = $this->assigned($actor, ['name' => 'Acuña · Villa Fontana · Sucursal 2', 'city' => 'Ciudad Sandino']);
        $this->assertSame('Acuña · Managua', $mine->display_name);
        $this->assertSame('Villa Fontana · Sucursal 2', $mine->branch_summary);
        $html = Livewire::actingAs($actor)->test(Pharmacies::class)->html();
        $this->assertStringContainsString('Acuña · Managua', $html);
        $this->assertStringContainsString('Managua, Ciudad Sandino', $html);
        $this->assertStringContainsString('Referencia: Villa Fontana · Sucursal 2', $html);
        $this->assertSame('Acuña · Villa Fontana · Sucursal 2', $mine->fresh()->name);
        $this->assertSame(0, PharmacyChange::count());
        $this->assertSame('Acuña · León', EstablishmentPresentation::title('Acuña · León', 'León'));
    }

    public function test_mysql_search_compiles_with_explicit_collation_and_bound_literal_terms(): void
    {
        $connection = new MySqlConnection(fn () => throw new \RuntimeException('A test must not connect to MySQL.'), 'isolated_compile_only');
        $query = (new Builder($connection->query()))->setModel(new Pharmacy);
        EstablishmentSearch::apply($query, 'Acu, 100%_!');
        $this->assertStringContainsString('COLLATE utf8mb4_unicode_ci', $query->toSql());
        $this->assertStringContainsString("ESCAPE '!'", $query->toSql());
        $this->assertSame(['%acu%', '%100!%!_!!%'], $query->getBindings());
    }
}
