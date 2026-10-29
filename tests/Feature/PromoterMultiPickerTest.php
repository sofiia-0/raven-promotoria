<?php

namespace Tests\Feature;

use App\Livewire\Promoter\Planning\Index;
use App\Livewire\Promoter\Visits\Register;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PromoterMultiPickerTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $actor;

    private array $pharmacies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 16:00:00', 'UTC'));
        $this->actor = User::factory()->promoter()->create();
        (new SchedulePlanner)->create($this->actor, 2026, 10);
        for ($i = 1; $i <= 6; $i++) {
            $p = Pharmacy::factory()->create(['name' => 'Ensayo múltiple '.$i, 'department' => 'Managua', 'city' => $i === 6 ? 'Tipitapa' : 'Managua', 'address' => 'Calle de prueba '.$i]);
            PharmacyAssignment::factory()->create(['pharmacy_id' => $p->id, 'promoter_id' => $this->actor->id]);
            $this->pharmacies[] = $p;
        }
    }

    public function test_five_choices_keep_one_query_and_another_query_keeps_the_selection(): void
    {
        $c = Livewire::actingAs($this->actor)->test(Index::class)->call('selectDay', '2026-10-20')->set('pharmacySearch', 'Managua');
        $ids = [];
        foreach (array_slice($this->pharmacies, 0, 5) as $p) {
            $ids[] = $p->id;
            $c->call('stagePharmacy', $p->id)->assertSet('pharmacySearch', 'Managua')->assertSet('selectedPharmacyIds', $ids);
        }
        $c->set('pharmacySearch', 'Tipitapa')->assertSet('selectedPharmacyIds', $ids)->call('stagePharmacy', $this->pharmacies[5]->id)->assertSet('pharmacySearch', 'Tipitapa')->assertSet('selectedPharmacyIds', [...$ids, $this->pharmacies[5]->id]);
        $this->assertSame(0, PlannedActivity::count());
    }

    public function test_mark_and_unmark_never_duplicate_an_id_and_removal_preserves_query(): void
    {
        $p = $this->pharmacies[0];
        $c = Livewire::actingAs($this->actor)->test(Index::class)->call('selectDay', '2026-10-20')->set('pharmacySearch', 'Managua')->call('stagePharmacy', $p->id)->assertSet('selectedPharmacyIds', [$p->id])->call('stagePharmacy', $p->id)->assertSet('selectedPharmacyIds', [])->assertSet('pharmacySearch', 'Managua')->call('stagePharmacy', $p->id)->call('removeStagedPharmacy', $p->id)->assertSet('selectedPharmacyIds', [])->assertSet('pharmacySearch', 'Managua');
        $this->assertSame(0, PlannedActivity::count());
    }

    public function test_current_plan_duplicates_and_foreign_pharmacies_remain_rejected(): void
    {
        $planner = new SchedulePlanner;
        $schedule = $planner->find($this->actor, 2026, 10);
        $planner->addActivity($this->actor, $schedule->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-10-20', 'pharmacy_id' => $this->pharmacies[0]->id]);
        $other = User::factory()->promoter()->create();
        $foreign = Pharmacy::factory()->create();
        PharmacyAssignment::factory()->create(['pharmacy_id' => $foreign->id, 'promoter_id' => $other->id]);
        $c = Livewire::actingAs($this->actor)->test(Index::class)->call('selectDay', '2026-10-20')->set('pharmacySearch', 'Managua')->call('stagePharmacy', $this->pharmacies[0]->id)->assertHasErrors('pharmacyId')->call('stagePharmacy', $foreign->id)->assertHasErrors('pharmacyId')->assertSet('selectedPharmacyIds', [])->assertSet('pharmacySearch', 'Managua');
        $this->assertSame(1, PlannedActivity::count());
    }

    public function test_multiple_aria_states_are_scoped_to_planning_and_confirmation_stays_separate(): void
    {
        $c = Livewire::actingAs($this->actor)->test(Index::class)->call('selectDay', '2026-10-20')->set('pharmacySearch', 'Managua');
        foreach (array_slice($this->pharmacies, 0, 5) as $p) {
            $c->call('stagePharmacy', $p->id);
        }
        $c->assertSee('aria-multiselectable="true"', false)->assertSee('Cerrar lista')->call('reviewBatch')->assertHasNoErrors();
        $this->assertSame(0, PlannedActivity::count());
        $c->call('confirmBatch')->assertHasNoErrors();
        $this->assertSame(5, PlannedActivity::count());
        Livewire::actingAs($this->actor)->test(Register::class)->assertDontSee('aria-multiselectable="true"', false)->assertDontSee('Cerrar lista')->set('pharmacyId',(string) $this->pharmacies[0]->id)->assertViewHas('selectedPharmacy',fn ($p) => $p->id === $this->pharmacies[0]->id);
    }
}
