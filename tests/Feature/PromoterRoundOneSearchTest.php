<?php

namespace Tests\Feature;

use App\Livewire\Promoter\Planning\Index as Planning;
use App\Livewire\Promoter\Visits\History;
use App\Livewire\Promoter\Visits\Register;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Services\SchedulePlanner;
use App\Services\VisitRecorder;
use App\Support\PharmacyAutocomplete;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PromoterRoundOneSearchTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 16:00:00', 'UTC'));
    }

    private function assigned(User $actor, array $values = []): Pharmacy
    {
        $p = Pharmacy::factory()->create(array_replace(['name' => 'Ensayo Acuña 7 · Sucursal Central', 'city' => 'Managua', 'department' => 'Managua', 'neighborhood' => 'Barrio Jardín', 'address' => 'Calle de ensayo 17', 'is_active' => true], $values));
        PharmacyAssignment::factory()->create(['pharmacy_id' => $p->id, 'promoter_id' => $actor->id]);

        return $p;
    }

    public function test_both_selectors_search_all_fields_and_keep_only_real_authorized_ids(): void
    {
        $actor = User::factory()->promoter()->create();
        $mine = $this->assigned($actor);
        PharmacyAssignment::factory()->create(['pharmacy_id' => $mine->id, 'promoter_id' => $actor->id]);
        $foreign = $this->assigned(User::factory()->promoter()->create(), ['name' => 'Establecimiento privado']);
        $inactive = $this->assigned($actor, ['name' => 'Inactivo de prueba', 'is_active' => false]);
        $ended = $this->assigned($actor, ['name' => 'Transferido de prueba']);
        $ended->assignments()->update(['ended_at' => now()]);
        (new SchedulePlanner)->create($actor, 2026, 10);
        foreach ([Planning::class, Register::class] as $c) {
            $test = Livewire::actingAs($actor)->test($c);
            foreach (['ACU, manágua', 'jardin 17', 'CENTRAL ensayo', 'calle managua'] as $search) {
                $test->set('pharmacySearch', $search)->assertViewHas('pharmacyOptions', fn ($items) => $items->pluck('id')->all() === [$mine->id]);
            }
            $test->set('pharmacyId', (string) $mine->id)->set('pharmacySearch', 'Sin coincidencias')->assertViewHas('selectedPharmacy', fn ($p) => $p?->id === $mine->id);
            foreach ([$foreign, $inactive, $ended] as $p) {
                $test->set('pharmacyId', (string) $p->id)->set('pharmacySearch', '')->assertViewHas('selectedPharmacy', fn ($p) => $p === null)->assertDontSee($p->name);
            }
            $test->set('pharmacyId', (string) $foreign->id);
            if ($c === Planning::class) {
                $test->set('scheduledDate', '2026-10-16')->call('addActivity')->assertHasErrors('pharmacyId');
            } else {
                $test->call('save')->assertHasErrors('pharmacyId');
            }
        }$this->assertSame(0, $mine->visits()->count());
    }

    public function test_limit_is_explicit_and_sql_wildcards_are_literal(): void
    {
        $actor = User::factory()->promoter()->create();
        for ($i = 0; $i < 21; $i++) {
            $this->assigned($actor, ['name' => 'Ensayo '.$i]);
        }$result = PharmacyAutocomplete::options($actor, 'ensayo', '');
        $this->assertCount(20, $result['pharmacyOptions']);
        $this->assertTrue($result['hasMorePharmacies']);
        $literal = $this->assigned($actor, ['name' => 'Ensayo 100%_7']);
        $this->assertSame([$literal->id], PharmacyAutocomplete::options($actor, '100%_7', '')['pharmacyOptions']->pluck('id')->all());
    }

    public function test_history_search_preserves_filters_ownership_and_access_after_transfer(): void
    {
        $actor = User::factory()->promoter()->create();
        $other = User::factory()->promoter()->create();
        $mine = $this->assigned($actor);
        $foreign = $this->assigned($other);
        $r = new VisitRecorder;
        $visit = $r->register($actor, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $mine->id]);
        $r->register($other, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $foreign->id]);
        $mine->assignments()->update(['ended_at' => now()]);
        $mine->update(['is_active' => false]);
        Livewire::actingAs($actor)->test(History::class)->set('search', 'ACU, jardín 17 central')->assertViewHas('visits', fn ($v) => $v->total() === 1 && $v->first()->id === $visit->id)
            ->set('status', 'NO_REALIZADA')->assertViewHas('visits', fn ($v) => $v->total() === 0)->set('status', 'REALIZADA')->set('origin', 'plan')->assertViewHas('visits', fn ($v) => $v->total() === 0)
            ->set('origin', 'fuera')->set('from', '2026-10-16')->assertViewHas('visits', fn ($v) => $v->total() === 0)->set('from', '2026-10-15')->set('to', '2026-10-15')->assertViewHas('visits', fn ($v) => $v->total() === 1)
            ->call('clearFilters')->assertSet('search', '')->assertSet('origin', '')->assertSet('status', '')->assertViewHas('visits', fn ($v) => $v->total() === 1);
    }
}
