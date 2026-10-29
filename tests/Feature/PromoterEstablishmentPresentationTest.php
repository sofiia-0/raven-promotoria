<?php

namespace Tests\Feature;

use App\Livewire\Promoter\Pharmacies\Show;
use App\Livewire\Promoter\PharmacyRequests\Index;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyChange;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Services\SchedulePlanner;
use App\Services\VisitRecorder;
use App\Support\EstablishmentPresentation as Presentation;
use App\Support\PharmacyAutocomplete;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PromoterEstablishmentPresentationTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private function assigned(User $actor, array $values = []): Pharmacy
    {
        $p = Pharmacy::factory()->create(array_replace([
            'name' => 'Ensayo 7 · León · Sucursal 2', 'department' => 'León', 'city' => 'León',
            'neighborhood' => 'Barrio Jardín', 'address' => 'Barrio Jardín, calle de ensayo 7.',
        ], $values));
        PharmacyAssignment::factory()->create(['pharmacy_id' => $p->id, 'promoter_id' => $actor->id]);

        return $p;
    }

    public function test_names_are_read_only_projections_and_never_lose_commercial_numbers_or_branch_identifiers(): void
    {
        foreach ([['Acuña · León', 'Acuña', ''], ['Farmadescuento · Managua', 'Farmadescuento', 'Managua'], ['Ensayo 74 · León · Sucursal 2', 'Ensayo 74', 'Sucursal 2'], ['Ensayo 74', 'Ensayo 74', ''], ['Ensayo · Avenida Leónidas', 'Ensayo', 'Avenida Leónidas']] as [$raw,$title,$branch]) {
            $p = new Pharmacy(['name' => $raw, 'department' => 'León', 'city' => 'León']);
            $before = $p->exists ? $p->fresh()->getAttributes() : $p->getAttributes();
            $this->assertSame($title, $p->display_name);
            $this->assertSame($branch, $p->branch_summary);
            $this->assertSame($before, $p->getAttributes());
        }
        $this->assertSame('Establecimiento', Presentation::title(null));
    }

    public function test_location_deduplicates_only_complete_provable_segments_and_preserves_free_text(): void
    {
        $this->assertSame('Managua, Managua', Presentation::locality('Managua', 'Managua'));
        $this->assertSame('León, Telica', Presentation::locality('León', 'Telica'));
        $this->assertSame('Barrio Jardín, calle 7.', Presentation::address('Barrio Jardín, calle 7.', 'barrio JARDIN', 'León', 'León'));
        $this->assertSame('Barrio Jardín, calle 7.', Presentation::address('calle 7.', 'Barrio Jardín', 'León', 'León'));
        $this->assertSame('Calle Leónidas, frente a León Comercial.', Presentation::address('Calle Leónidas, frente a León Comercial.', 'León', 'León', 'León'));
        $this->assertSame('Barrio Jardín, calle 7', Presentation::address('León, Barrio Jardín, calle 7, León', 'Barrio Jardín', 'León', 'León'));
        $this->assertSame('Barrio 2, Barrio 20, calle 7', Presentation::address('Barrio 20, calle 7', 'Barrio 2'));
        $this->assertSame('Barrio Jardín', Presentation::address(null, 'Barrio Jardín'));
        $this->assertSame('', Presentation::address(null, null));
        $this->assertSame('Sucursal 2', Presentation::secondaryReference('Sucursal 2 · Barrio Jardín', 'Barrio Jardín, calle 7.'));
        $this->assertSame('', Presentation::secondaryReference('Sucursal 2', 'Sucursal 2, módulo 7.'));
        $this->assertSame('Calle 7; frente al parque.\nEntrada norte', Presentation::address('Calle 7; frente al parque.\nEntrada norte', null));
    }

    public function test_open_and_save_unchanged_preserves_every_historical_column_and_has_no_audit_side_effect(): void
    {
        $actor = User::factory()->promoter()->create();
        foreach ([['address' => null], ['address' => 'Barrio Jardín, calle de ensayo 7.'], ['department' => 'Ubicación histórica', 'city' => 'Municipio histórico']] as $values) {
            $p = $this->assigned($actor, $values);
            $before = $p->exists ? $p->fresh()->getAttributes() : $p->getAttributes();
            $component = Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $p->id]);
            $component->assertSet('name', 'Ensayo 7')->assertDontSee('Barrio / zona')->assertDontSee('Se conserva la información histórica')->assertDontSee('Los datos históricos se conservan')->assertSee('No es necesario repetir el departamento ni el municipio');
            $component->call('save')->assertHasNoErrors();
            $this->assertSame($before, $p->fresh()->getAttributes());
            $this->assertSame(0, $p->changes()->count());
        }
    }

    public function test_unrelated_and_explicit_address_edits_preserve_the_uneditable_historical_neighborhood(): void
    {
        $actor = User::factory()->promoter()->create();
        $p = $this->assigned($actor, ['address' => null]);
        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $p->id])->set('phone', '2222-1111')->call('save')->assertHasNoErrors();
        $this->assertSame('Barrio Jardín', $p->fresh()->neighborhood);
        $this->assertNull($p->fresh()->address);
        $this->assertSame(['phone'], array_keys($p->changes()->sole()->after));
        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $p->id])->set('address', 'Barrio Jardín, frente al parque.')->call('save')->assertHasNoErrors();
        $this->assertSame('Barrio Jardín', $p->fresh()->neighborhood);
        $this->assertSame('Barrio Jardín, frente al parque.', $p->fresh()->display_address);
        $this->assertSame(['address'], array_keys($p->changes()->latest('id')->first()->after));
    }

    public function test_explicit_commercial_name_edit_preserves_historical_branch_suffix_and_audit(): void
    {
        $actor = User::factory()->promoter()->create();
        $p = $this->assigned($actor);
        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $p->id])->set('name', 'Ensayo 8')->call('save')->assertHasNoErrors();
        $this->assertSame('Ensayo 8', $p->fresh()->display_name);
        $this->assertSame('Ensayo 8 · León · Sucursal 2', $p->fresh()->name);
        $this->assertSame('Sucursal 2', $p->fresh()->branch_summary);
        $this->assertSame(['name' => 'Ensayo 7 · León · Sucursal 2'], $p->changes()->sole()->before);
        $this->assertSame(['name' => 'Ensayo 8 · León · Sucursal 2'], $p->changes()->sole()->after);
    }

    public function test_repeated_names_remain_distinguishable_by_scoped_id_address_and_branch(): void
    {
        $actor = User::factory()->promoter()->create();
        $other = User::factory()->promoter()->create();
        $first = $this->assigned($actor);
        $second = $this->assigned($actor, ['name' => 'Ensayo 7 · Sucursal Norte', 'address' => 'Calle de ensayo 8.']);
        $foreign = $this->assigned($other);
        $options = PharmacyAutocomplete::options($actor, 'ENSAYO, leon', '')['pharmacyOptions'];
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $options->modelKeys());
        $this->assertSame($first->display_name, $second->display_name);
        $this->assertNotSame($first->display_address, $second->display_address);
        $this->assertNotSame($first->branch_summary, $second->branch_summary);
        $this->assertNull(PharmacyAutocomplete::options($actor, '', (string) $foreign->id)['selectedPharmacy']);
        $this->actingAs($actor)->get('/promoter/visits/register')->assertOk()->assertDontSee('promoter-branch-label')->assertSee('calle de ensayo 7.')->assertSee('Calle de ensayo 8.');
    }

    public function test_only_indistinguishable_selector_options_receive_text_identifiers_without_hiding_records(): void
    {
        $actor = User::factory()->promoter()->create();
        $other = User::factory()->promoter()->create();
        $first = $this->assigned($actor);
        $second = $this->assigned($actor, ['name' => 'Ensayo 7 · Sucursal Norte']);
        $foreign = $this->assigned($other, ['name' => 'Ensayo 7 · Privado']);
        $options = PharmacyAutocomplete::options($actor, 'ensayo', '')['pharmacyOptions'];
        $identifiers = Presentation::selectionIdentifiers($options);
        $this->assertSame([$first->id => 'Sucursal 2', $second->id => 'Sucursal Norte'], $identifiers);
        $this->assertCount(2, $options);
        $this->assertArrayNotHasKey($foreign->id, $identifiers);
        $this->assertSame([], Presentation::selectionIdentifiers([$first]));
        $second->address = 'Otra dirección de prueba.';
        $this->assertSame([], Presentation::selectionIdentifiers([$first, $second]));
        $this->actingAs($actor)->get('/promoter/visits/register')->assertOk()
            ->assertSee('promoter-picker-identifier')->assertSee('— Sucursal Norte')->assertDontSee('promoter-branch-label');
        Livewire::actingAs($actor)->test(Index::class)
            ->call('openForm', 'baja')->assertSee('— Sucursal Norte')->assertDontSee('promoter-branch-label');
        $this->assertSame('Ensayo 7 · León · Sucursal 2', $first->fresh()->name);
        $this->assertSame(0, $first->changes()->count());
    }

    public function test_identical_historical_references_use_existing_record_identifiers_only_in_the_selector(): void
    {
        $first = new Pharmacy(['name' => 'Ensayo · Central', 'department' => 'León', 'city' => 'León', 'address' => 'Calle 7', 'source_reference' => 'TEST-1']);
        $first->id = 51;
        $second = new Pharmacy($first->getAttributes());
        $second->id = 52;
        $second->source_reference = null;
        $this->assertSame([51 => 'TEST-1', 52 => 'Registro 52'], Presentation::selectionIdentifiers([$first, $second]));
        $this->assertStringNotContainsString('Central', $first->location_summary);
    }

    public function test_all_promoter_surfaces_share_the_same_location_without_standalone_reference_lines(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 16:00:00', 'UTC'));
        $actor = User::factory()->promoter()->create();
        $p = $this->assigned($actor);
        $before = $p->exists ? $p->fresh()->getAttributes() : $p->getAttributes();
        PharmacyChange::create(['pharmacy_id' => $p->id, 'actor_id' => $actor->id, 'before' => ['phone' => null], 'after' => ['phone' => '2222']]);
        PharmacyRequest::create(['promoter_id' => $actor->id, 'pharmacy_id' => $p->id, 'type' => 'baja', 'status' => 'pendiente', 'data' => [], 'reason' => 'Motivo sintético de revisión.']);
        $planner = new SchedulePlanner;
        $schedule = $planner->create($actor, 2026, 10);
        $activity = $planner->addActivity($actor, $schedule->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-10-15', 'pharmacy_id' => $p->id]);
        $planner->submit($actor, $schedule->id);
        $visit = (new VisitRecorder)->register($actor, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $p->id]);
        $this->actingAs($actor);
        foreach (['/promoter', '/promoter/pharmacies', '/promoter/pharmacies/'.$p->id, '/promoter/requests', '/promoter/planning?mes=2026-10', '/promoter/visits', '/promoter/visits/register?actividad='.$activity->id, '/promoter/visits/history', '/promoter/visits/'.$visit->id] as $path) {
            $response = $this->get($path)->assertOk()->assertSee('Ensayo 7')->assertDontSee('Referencia:');
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
            $xpath = new \DOMXPath($dom);
            $locations = $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," promoter-location ")]');
            $this->assertGreaterThan(0, $locations->length, $path);
            foreach ($locations as $location) {
                $text = $location->textContent;
                $this->assertStringContainsString('León, León', $text, $path);
                $this->assertStringNotContainsString('Sucursal 2', $text, $path);
                $this->assertSame(1, substr_count($text, 'Barrio Jardín'), $path);
                $this->assertStringContainsString('calle de ensayo 7.', $text, $path);
            }
        }
        $this->assertSame($before, $p->fresh()->getAttributes());
    }
}
