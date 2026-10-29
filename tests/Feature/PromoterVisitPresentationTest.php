<?php

namespace Tests\Feature;

use App\Livewire\Promoter\Visits\Register;
use App\Models\ChecklistItem;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Models\Visit;
use App\Services\OfficialVisitChecklist;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PromoterVisitPresentationTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $actor;

    private Pharmacy $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 16:00:00', 'UTC'));
        (new OfficialVisitChecklist)->seedFresh();
        $this->actor = User::factory()->promoter()->create();
        $this->pharmacy = Pharmacy::factory()->create(['name' => 'Ensayo 23 · Sucursal Central', 'city' => 'León', 'department' => 'León', 'neighborhood' => 'Barrio de ensayo', 'address' => 'Barrio de ensayo, calle 23.']);
        PharmacyAssignment::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'promoter_id' => $this->actor->id]);
    }

    public function test_plan_context_and_location_are_read_only_and_all_required_controls_share_one_form(): void
    {
        $planner = new SchedulePlanner;
        $schedule = $planner->create($this->actor, 2026, 10);
        $activity = $planner->addActivity($this->actor, $schedule->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-10-15', 'pharmacy_id' => $this->pharmacy->id]);
        $planner->submit($this->actor, $schedule->id);
        $before = $this->pharmacy->fresh()->getAttributes();
        $html = $this->actingAs($this->actor)->get('/promoter/visits/register?actividad='.$activity->id)->assertOk()
            ->assertSee('Ensayo 23')->assertSee('Barrio de ensayo, calle 23.')->getContent();
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $x = new DOMXPath($doc);
        $this->assertSame(1, $x->query('//form[@id="promoter-record-form"]')->length);
        $this->assertSame(6, $x->query('//form[@id="promoter-record-form"]//input[@type="checkbox"]')->length);
        $this->assertSame(2, $x->query('//form[@id="promoter-record-form"]//input[@type="radio"]')->length);
        $this->assertSame(1, $x->query('//form[@id="promoter-record-form"]//textarea[@id="visit-notes"]')->length);
        $this->assertSame(0, $x->query('//aside//input')->length);
        $this->assertSame(3, $x->query('//aside//button[@type="button"]')->length);
        $this->assertSame(0, $x->query('//aside//button[@type="submit"]')->length);
        $this->assertSame($before, $this->pharmacy->fresh()->getAttributes());
        $this->assertSame(0, Visit::count());
    }

    public function test_outside_plan_registration_preserves_six_answers_and_optional_location_from_component_state(): void
    {
        $ids = ChecklistItem::active()->pluck('id');
        Livewire::actingAs($this->actor)->test(Register::class)
            ->set('pharmacyId', (string) $this->pharmacy->id)
            ->set('checklist', [$ids[0] => true, $ids[5] => true])
            ->set('latitude', '12.1234567')->set('longitude', '-86.1234567')->set('locationAccuracy', 20)
            ->call('save')->assertHasNoErrors();
        $visit = Visit::sole();
        $this->assertSame($this->pharmacy->id, $visit->pharmacy_id);
        $this->assertCount(6, $visit->checklist);
        $this->assertSame(2, $visit->checklist->where('checked', true)->count());
        $this->assertSame('12.1234567', $visit->latitude);
        $this->assertSame('-86.1234567', $visit->longitude);
        $this->assertSame(20, $visit->location_accuracy);
    }
}
