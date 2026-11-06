<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Catalogs\Index;
use App\Models\AuditLog;
use App\Models\ChecklistItem;
use App\Models\Pharmacy;
use App\Models\PromoterReasonCategory;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitChecklistEntry;
use App\Models\VisitReason;
use App\Services\CoordinatorCatalogs;
use App\Services\VisitContextRecorder;
use App\Support\CanonicalData;
use App\Support\PromoterCatalogOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class CoordinatorCatalogsTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    public function test_coordinator_options_are_dynamic_but_do_not_replace_historical_values(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $promoter = User::factory()->promoter()->create();
        $service = app(CoordinatorCatalogs::class);
        $record = $service->save($coordinator, 'client_type', null, ['name' => 'Tipo verificado', 'sort_order' => 2, 'is_active' => true]);
        $this->assertSame(['Tipo verificado'], PromoterCatalogOptions::values($promoter, 'client_type')->all());
        $service->save($coordinator, 'client_type', $record->id, ['name' => $record->name, 'sort_order' => 1, 'is_active' => false], $service->fingerprint($record));
        $this->assertCount(0, PromoterCatalogOptions::values($promoter, 'client_type'));
        $this->assertSame(['value' => 'Valor histórico', 'proposed' => false], PromoterCatalogOptions::resolveCommercial($promoter, 'client_type', 'VALOR HISTORICO', 'Valor histórico'));
        $this->assertSame(2, AuditLog::where('actor_id', $coordinator->id)->count());
    }

    public function test_commercial_categories_are_fixed_even_for_a_coordinator(): void
    {
        $actor = User::factory()->coordinator()->create();

        Livewire::actingAs($actor)->test(Index::class)
            ->set('domain', 'category')->assertSee('No aplica')->assertDontSee('Añadir opción')
            ->call('startNew')->assertForbidden();
        Livewire::actingAs($actor)->test(Index::class)
            ->set('domain', 'category')->set('name', 'Categoría libre')
            ->call('save')->assertForbidden();
        $this->assertDatabaseCount('commercial_catalog_options', 0);
    }

    public function test_duplicate_names_ignore_accents_and_case(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $service = app(CoordinatorCatalogs::class);
        $service->save($coordinator, 'checklist', null, ['name' => 'Capacitación', 'sort_order' => 1, 'is_active' => false]);
        $this->expectException(ValidationException::class);
        $service->save($coordinator, 'checklist', null, ['name' => '  CAPACITACION ', 'sort_order' => 2, 'is_active' => true]);
    }

    public function test_renaming_a_used_checklist_creates_a_version_and_preserves_answers(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $promoter = User::factory()->promoter()->create();
        $item = ChecklistItem::create(['name' => 'Actividad anterior', 'sort_order' => 1, 'is_active' => true]);
        $visit = Visit::create(['idempotency_key' => (string) Str::uuid(), 'promoter_id' => $promoter->id, 'pharmacy_id' => Pharmacy::factory()->create()->id, 'status' => 'REALIZADA', 'attempt' => null, 'visited_at' => now()]);
        $answer = VisitChecklistEntry::create(['visit_id' => $visit->id, 'checklist_item_id' => $item->id, 'checked' => true]);
        $service = app(CoordinatorCatalogs::class);
        $next = $service->save($coordinator, 'checklist', $item->id, ['name' => 'Actividad revisada', 'sort_order' => 1, 'is_active' => true], $service->fingerprint($item));
        $this->assertNotSame($item->id, $next->id);
        $this->assertFalse($item->fresh()->is_active);
        $this->assertSame('Actividad anterior', $answer->fresh()->item->name);
        $this->assertTrue($answer->fresh()->checked);
        $this->assertSame(['Actividad revisada'], ChecklistItem::active()->pluck('name')->all());
    }

    public function test_stale_edits_are_rejected(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $service = app(CoordinatorCatalogs::class);
        $item = $service->save($coordinator, 'visit_reason', null, ['name' => 'Motivo verificado', 'sort_order' => 1, 'is_active' => true]);
        $expected = $service->fingerprint($item);
        $item->update(['sort_order' => 4]);
        $this->expectException(ValidationException::class);
        $service->save($coordinator, 'visit_reason', $item->id, ['name' => $item->name, 'sort_order' => 2, 'is_active' => true], $expected);
    }

    public function test_livewire_revalidates_permissions_and_saves_structured_categories(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $this->actingAs($coordinator)->get(route('coordinator.catalogs.index'))->assertOk();
        Livewire::actingAs($coordinator)->test(Index::class)->set('domain', 'request_alta')->set('name', 'Otro motivo revisado')->set('other', true)->call('save')->assertHasNoErrors();
        $option = PromoterReasonCategory::firstOrFail();
        $this->assertTrue($option->is_other);
        $this->assertTrue($option->is_provisional);
        $coordinator->update(['is_active' => false]);
        Livewire::actingAs($coordinator)->test(Index::class)->assertForbidden();
        $this->actingAs(User::factory()->promoter()->create())->get(route('coordinator.catalogs.index'))->assertForbidden();
    }

    public function test_custom_reason_can_require_explanation_without_a_special_name(): void
    {
        $actor = User::factory()->coordinator()->create();
        $reason = app(CoordinatorCatalogs::class)->save($actor, 'visit_reason', null, ['name' => 'Incidencia que necesita detalle', 'is_active' => true, 'sort_order' => 1, 'is_other' => true]);

        $this->assertTrue(app(VisitContextRecorder::class)->requiresReasonExplanation($reason->id));
        $legacy = VisitReason::create(['name' => 'Otro motivo', 'sort_order' => 2, 'is_active' => true]);
        $this->assertTrue(app(VisitContextRecorder::class)->requiresReasonExplanation($legacy->id));
    }

    public function test_admin_and_guest_cannot_manage_catalogs_and_names_are_escaped(): void
    {
        $this->get(route('coordinator.catalogs.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))->get(route('coordinator.catalogs.index'))->assertForbidden();
        $coordinator = User::factory()->coordinator()->create();
        ChecklistItem::create(['name' => '<script>alert(1)</script>', 'sort_order' => 1, 'is_active' => true]);
        Livewire::actingAs($coordinator)->test(Index::class)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_observed_options_need_confirmation_and_do_not_modify_establishments(): void
    {
        $actor = User::factory()->coordinator()->create();
        $first = Pharmacy::factory()->create(['client_type' => 'Farmacia', 'category' => '1']);
        $second = Pharmacy::factory()->create(['client_type' => 'FARMÁCIA', 'category' => '1']);
        Pharmacy::factory()->create(['client_type' => 'Valor retirado', 'is_active' => false]);
        $before = [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()];
        $service = app(CoordinatorCatalogs::class);
        $observed = $service->observedCommercial($actor, 'client_type');
        $this->assertCount(1, $observed);
        $this->assertSame(2, $observed[0]['count']);
        $this->assertDatabaseCount('commercial_catalog_options', 0);

        $this->assertSame(1, $service->adoptCommercial($actor, 'client_type', ['farmacia'], CanonicalData::hash($observed)));
        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()]);
        $this->assertDatabaseCount('pharmacy_changes', 0);
        $this->assertSame(['Farmacia'], PromoterCatalogOptions::values(User::factory()->promoter()->create(), 'client_type')->all());
        Livewire::actingAs($actor)->test(Index::class)->set('domain', 'client_type')->assertSee('Historial del catálogo')->assertSee('Opción creada');
    }

    public function test_revising_a_used_visit_reason_does_not_reinterpret_history(): void
    {
        $actor = User::factory()->coordinator()->create();
        $reason = VisitReason::create(['name' => 'Otro motivo', 'sort_order' => 1, 'is_active' => true]);
        $visit = Visit::create(['idempotency_key' => (string) Str::uuid(), 'promoter_id' => User::factory()->promoter()->create()->id, 'pharmacy_id' => Pharmacy::factory()->create()->id, 'status' => 'NO_REALIZADA', 'visit_reason_id' => $reason->id, 'notes' => 'Explicación histórica conservada', 'visited_at' => now()]);
        $service = app(CoordinatorCatalogs::class);

        $next = $service->save($actor, 'visit_reason', $reason->id, ['name' => 'Nuevo motivo revisado', 'sort_order' => 1, 'is_active' => true, 'is_other' => false], $service->fingerprint($reason));

        $this->assertNotSame($reason->id, $next->id);
        $this->assertSame('Otro motivo', $visit->fresh()->reason->name);
        $this->assertSame('Explicación histórica conservada', $visit->fresh()->notes);
        $this->assertFalse($reason->fresh()->is_active);
        $this->assertFalse($next->needs_explanation);
    }
}
