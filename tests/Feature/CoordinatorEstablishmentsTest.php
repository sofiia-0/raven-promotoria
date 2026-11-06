<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Establishments\Index;
use App\Livewire\Coordinator\Establishments\Show;
use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Models\Visit;
use App\Services\CoordinatorEstablishments;
use App\Support\CoordinatorEstablishmentQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class CoordinatorEstablishmentsTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    public function test_shared_assignments_and_partial_search_return_one_establishment(): void
    {
        $actor = User::factory()->coordinator()->create();
        $pharmacy = Pharmacy::factory()->create(['name' => 'Acuña · León', 'department' => 'Managua', 'city' => 'Managua', 'address' => 'Frente al mercado', 'category' => 'A']);
        $people = User::factory()->promoter()->count(2)->create();
        foreach ($people as $person) {
            PharmacyAssignment::factory()->create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $person->id]);
        }
        Pharmacy::factory()->create(['name' => 'Otro local', 'department' => 'León']);

        $this->assertSame([$pharmacy->id], CoordinatorEstablishmentQuery::for($actor, ['search' => 'ACU, mercado', 'department' => 'Managua', 'city' => 'Managua', 'category' => 'A', 'promoter' => (string) $people[1]->id])->pluck('id')->all());
        Livewire::actingAs($actor)->test(Index::class)->set('search', 'acu, mercado')->assertSee('Catálogo central')->assertSee('Acuña');
    }

    public function test_saving_an_unchanged_historical_form_does_not_rewrite_any_data(): void
    {
        $actor = User::factory()->coordinator()->create();
        $pharmacy = Pharmacy::factory()->create(['name' => 'Marca · Sucursal 2 · León', 'department' => 'Ubicación histórica', 'city' => null, 'address' => null, 'neighborhood' => 'Barrio conservado', 'category' => 'Anterior']);
        $before = $pharmacy->fresh()->getRawOriginal();

        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $pharmacy->id])->call('save')->assertHasNoErrors();

        $this->assertSame($before, $pharmacy->fresh()->getRawOriginal());
        $this->assertDatabaseCount('pharmacy_changes', 0);
    }

    public function test_coordinator_deactivation_preserves_assignments_visits_and_source_reference(): void
    {
        $actor = User::factory()->coordinator()->create();
        $promoter = User::factory()->promoter()->create();
        $pharmacy = Pharmacy::factory()->create(['name' => 'Marca · Sucursal 2', 'source_reference' => 'TEST-42']);
        $assignment = PharmacyAssignment::factory()->create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $promoter->id]);
        $visit = Visit::create(['idempotency_key' => (string) Str::uuid(), 'promoter_id' => $promoter->id, 'pharmacy_id' => $pharmacy->id, 'status' => 'REALIZADA', 'attempt' => null, 'visited_at' => now()]);
        $service = app(CoordinatorEstablishments::class);

        $service->update($actor, $pharmacy->id, ['is_active' => false, 'source_reference' => 'FORGED', 'id' => 100], $service->fingerprint($pharmacy));

        $this->assertFalse($pharmacy->fresh()->is_active);
        $this->assertSame('TEST-42', $pharmacy->fresh()->source_reference);
        $this->assertSame($pharmacy->id, $visit->fresh()->pharmacy_id);
        $this->assertSame($promoter->id, $visit->fresh()->promoter_id);
        $this->assertNull($assignment->fresh()->ended_at);
        $this->assertDatabaseHas('pharmacy_changes', ['pharmacy_id' => $pharmacy->id, 'actor_id' => $actor->id]);
        $this->assertSame(1, AuditLog::where('action', 'establishment.updated')->count());
    }

    public function test_concurrent_changes_are_rejected_without_overwriting(): void
    {
        $actor = User::factory()->coordinator()->create();
        $pharmacy = Pharmacy::factory()->create(['address' => 'Dirección original']);
        $service = app(CoordinatorEstablishments::class);
        $expected = $service->fingerprint($pharmacy);
        $pharmacy->update(['address' => 'Dirección más reciente']);
        try {
            $service->update($actor, $pharmacy->id, ['address' => 'Dirección desactualizada'], $expected);
            $this->fail('Expected a concurrent modification conflict.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('establishment', $error->errors());
        }
        $this->assertSame('Dirección más reciente', $pharmacy->fresh()->address);
        $this->assertDatabaseCount('pharmacy_changes', 0);
    }

    public function test_geography_is_validated_and_historical_suffix_is_retained(): void
    {
        $actor = User::factory()->coordinator()->create();
        $pharmacy = Pharmacy::factory()->create(['name' => 'Marca · Sucursal 2', 'department' => 'Managua', 'city' => 'Managua']);
        $service = app(CoordinatorEstablishments::class);
        $service->update($actor, $pharmacy->id, ['name' => 'Marca revisada'], $service->fingerprint($pharmacy));
        $this->assertSame('Marca revisada · Sucursal 2', $pharmacy->fresh()->name);

        Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $pharmacy->id])->set('form.department', 'León')->set('form.city', 'Managua')->call('save')->assertHasErrors('form.city');
        $this->assertSame('Managua', $pharmacy->fresh()->department);
    }

    public function test_only_active_coordinators_can_view_and_edit_the_central_catalog(): void
    {
        $pharmacy = Pharmacy::factory()->create();
        $this->get(route('coordinator.establishments.index'))->assertRedirect(route('login'));
        foreach ([UserRole::ADMIN, UserRole::PROMOTER] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('coordinator.establishments.show', $pharmacy->id))->assertForbidden();
            Livewire::actingAs($actor)->test(Show::class, ['pharmacyId' => $pharmacy->id])->assertForbidden();
        }
        $coordinator = User::factory()->coordinator()->create();
        Livewire::actingAs($coordinator)->test(Show::class, ['pharmacyId' => $pharmacy->id])->assertSee('Historial de modificaciones')->assertSee('Todas las visitas');
        $coordinator->update(['is_active' => false]);
        Livewire::actingAs($coordinator)->test(Index::class)->assertForbidden();
    }
}
