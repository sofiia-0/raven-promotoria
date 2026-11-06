<?php

namespace Tests\Feature;

use App\Livewire\Coordinator\Requests\Index as Requests;
use App\Livewire\Coordinator\Supervision\Visits;
use App\Models\ChecklistItem;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Services\VisitRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class CoordinatorIntegrationConsistencyTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    public function test_requests_search_parses_unicode_json_and_terms_across_fields(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $promoter = User::factory()->promoter()->create();
        foreach ([['name' => 'Acuña de prueba', 'department' => 'León', 'city' => 'Telica', 'address' => 'Barrio Jardín, casa 12'], ['name' => 'Otra solicitud', 'department' => 'Managua', 'city' => 'Managua', 'address' => 'Centro']] as $data) {
            PharmacyRequest::create(['promoter_id' => $promoter->id, 'type' => 'alta', 'status' => 'pending', 'data' => $data, 'reason' => 'Solicitud de prueba']);
        }
        Livewire::actingAs($coordinator)->test(Requests::class)->set('search', 'ACU, léon jardin')
            ->assertSee('Acuña de prueba')->assertDontSee('Otra solicitud');
    }

    public function test_visits_search_includes_department_and_verified_address(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00:00', 'UTC'));
        $coordinator = User::factory()->coordinator()->create();
        $promoter = User::factory()->promoter()->create();
        $pharmacy = Pharmacy::factory()->create(['name' => 'Acuña de prueba', 'department' => 'León', 'city' => 'Telica', 'address' => 'Barrio Jardín, casa 12']);
        PharmacyAssignment::factory()->create(['promoter_id' => $promoter->id, 'pharmacy_id' => $pharmacy->id]);
        (new VisitRecorder)->register($promoter, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $pharmacy->id]);
        Livewire::actingAs($coordinator)->test(Visits::class)->set('search', 'ACU, leon jardin')->assertSee('Acuña de prueba');
        Livewire::actingAs($coordinator)->test(Visits::class)->set('search', 'Acu, Estelí')->assertDontSee('Acuña de prueba');
    }

    public function test_coordinator_checklist_groups_checked_first_without_reinterpreting_labels(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $promoter = User::factory()->promoter()->create();
        $pharmacy = Pharmacy::factory()->create();
        PharmacyAssignment::factory()->create(['promoter_id' => $promoter->id, 'pharmacy_id' => $pharmacy->id]);
        $old = ChecklistItem::factory()->create(['name' => 'Capacitación o revisión de plan de exhibición', 'sort_order' => 1]);
        $checked = ChecklistItem::factory()->create(['name' => 'Actividad histórica marcada', 'sort_order' => 2]);
        $visit = (new VisitRecorder)->register($promoter, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $pharmacy->id, 'checklist' => [$checked->id => true]]);
        $this->assertFalse($visit->checklist()->where('checklist_item_id', $old->id)->exists());
        $visit->checklist()->create(['checklist_item_id' => $old->id, 'checked' => false]);
        $before = $visit->checklist()->orderBy('id')->get()->toJson();
        $this->assertFalse(ChecklistItem::active()->whereKey($old->id)->exists());
        $response = $this->actingAs($coordinator)->get('/coordinator/visits/'.$visit->id)->assertOk()->assertSee('Capacitación o revisión de plan de exhibición')->assertSee('Opción histórica');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $lists = $xpath->query('//ul[@aria-label="Realizada" or @aria-label="Sin marcar"]');
        $this->assertSame('Realizada', $lists->item(0)->getAttribute('aria-label'));
        $this->assertStringContainsString($checked->name, $lists->item(0)->textContent);
        $this->assertStringContainsString($old->name, $lists->item(1)->textContent);
        $this->assertSame($before, $visit->checklist()->orderBy('id')->get()->toJson());
    }
}
