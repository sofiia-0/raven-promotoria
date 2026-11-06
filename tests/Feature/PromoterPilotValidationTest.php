<?php

namespace Tests\Feature;

use App\Livewire\Promoter\Planning\Index as Planning;
use App\Livewire\Promoter\Visits\Register;
use App\Models\ChecklistItem;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PromoterReasonCategory;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReason;
use App\Services\OfficialVisitChecklist;
use App\Services\SchedulePlanner;
use App\Services\VisitContextRecorder;
use App\Services\VisitRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PromoterPilotValidationTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $actor;

    private Pharmacy $pharmacy;

    private PromoterReasonCategory $objective;

    private VisitContextRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 16:00:00', 'UTC'));
        $this->actor = User::factory()->promoter()->create();
        $this->pharmacy = Pharmacy::factory()->create();
        PharmacyAssignment::factory()->create(['promoter_id' => $this->actor->id, 'pharmacy_id' => $this->pharmacy->id]);
        $this->objective = PromoterReasonCategory::create(['domain' => 'visit_objective', 'code' => 'commercial_followup', 'name' => 'Seguimiento comercial', 'is_active' => true, 'is_provisional' => true]);
        $this->recorder = new VisitContextRecorder;
    }

    private function data(array $extra = []): array
    {
        return [...['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $this->pharmacy->id, 'latitude' => '12.1234567', 'longitude' => '-86.1234567', 'location_accuracy' => 12, 'notes' => 'Observación independiente.'], ...$extra];
    }

    private function reject(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Expected validation error.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_new_recording_requires_a_catalog_objective_and_captured_location_on_server_and_form(): void
    {
        $this->reject(fn () => $this->recorder->register($this->actor, $this->data()), 'objectiveId');
        foreach (['latitude', 'longitude'] as $field) {
            $this->reject(fn () => $this->recorder->register($this->actor, $this->data([$field => null]), [], $this->objective->id), $field);
        }
        Livewire::actingAs($this->actor)->test(Register::class)->set('pharmacyId', (string) $this->pharmacy->id)->call('save')->assertHasErrors(['objectiveId', 'latitude', 'longitude']);
        $this->assertSame(0, Visit::count());
    }

    public function test_location_coordinates_must_be_numeric_and_in_range_and_accuracy_valid(): void
    {
        foreach ([['latitude', 'abc'], ['latitude', 91], ['latitude', -91], ['longitude', 181], ['longitude', -181], ['longitude', ''], ['location_accuracy', -1], ['location_accuracy', 2147483648]] as [$field,$value]) {
            $this->reject(fn () => $this->recorder->register($this->actor, $this->data([$field => $value]), [], $this->objective->id), $field);
        }
        $visit = $this->recorder->register($this->actor, $this->data(['latitude' => '0', 'longitude' => '0', 'location_accuracy' => null]), [], $this->objective->id);
        $this->assertTrue($visit->hasLocation());
        $this->assertNull($visit->location_accuracy);
    }

    public function test_realized_visit_keeps_objective_observations_and_seven_checklist_answers_separate(): void
    {
        (new OfficialVisitChecklist)->seedFresh();
        $ids = ChecklistItem::active()->pluck('id');
        $visit = $this->recorder->register($this->actor, $this->data(['checklist' => [$ids[0] => true, $ids[2] => true]]), [], $this->objective->id);
        $this->assertSame('Observación independiente.', $visit->notes);
        $this->assertSame(12, $visit->location_accuracy);
        $this->assertSame('Seguimiento comercial', $visit->objective->category_label);
        $this->assertCount(7, $visit->checklist);
        $this->assertSame(2, $visit->checklist->where('checked', true)->count());
        $this->actingAs($this->actor)->get('/promoter/visits/'.$visit->id)->assertOk()->assertSee('Seguimiento comercial')->assertSee('Observación independiente.')->assertSeeInOrder(['Realizada · 2', 'Sin marcar · 5']);
    }

    public function test_other_objective_remains_a_catalog_option_and_requires_its_own_explanation(): void
    {
        $other = PromoterReasonCategory::create(['domain' => 'visit_objective', 'code' => 'other', 'name' => 'Otro (especificar)', 'is_other' => true, 'is_active' => true]);
        $this->reject(fn () => $this->recorder->register($this->actor, $this->data(), [], $other->id, 'Breve'), 'objectiveExplanation');
        $visit = $this->recorder->register($this->actor, $this->data(), [], $other->id, 'Objetivo propuesto de prueba.');
        $this->assertSame('Objetivo propuesto de prueba.', $visit->objective->explanation);
        $this->assertSame('Observación independiente.', $visit->notes);
        $this->assertSame(2, PromoterReasonCategory::count());
    }

    public function test_inactive_objectives_and_categories_from_other_domains_are_rejected(): void
    {
        $wrong = PromoterReasonCategory::create(['domain' => 'request_alta', 'code' => 'other', 'name' => 'Otro', 'is_active' => true]);
        $this->reject(fn () => $this->recorder->register($this->actor, $this->data(), [], $wrong->id), 'objectiveId');
        $this->objective->update(['is_active' => false]);
        $this->reject(fn () => $this->recorder->register($this->actor, $this->data(), [], $this->objective->id), 'objectiveId');
        $this->assertSame(0, Visit::count());
    }

    public function test_not_realized_visits_require_reason_and_location_but_not_an_objective(): void
    {
        $this->reject(fn () => $this->recorder->register($this->actor, $this->data(['status' => 'NO_REALIZADA'])), 'reasonId');
        $reason = VisitReason::factory()->create(['name' => 'Otro motivo']);
        $data = $this->data(['status' => 'NO_REALIZADA', 'visit_reason_id' => $reason->id, 'notes' => 'Breve']);
        $this->reject(fn () => $this->recorder->register($this->actor, $data), 'notes');
        $data['notes'] = 'Explicación suficiente del motivo.';
        $data['latitude'] = null;
        $this->reject(fn () => $this->recorder->register($this->actor, $data), 'latitude');
        $data['latitude'] = '12.1';
        $visit = $this->recorder->register($this->actor, $data);
        $this->assertNull($visit->objective);
        $this->assertCount(0, $visit->checklist);
    }

    public function test_old_visits_without_coordinates_or_objectives_are_not_reinterpreted_and_replays_do_not_append(): void
    {
        $data = $this->data(['latitude' => null, 'longitude' => null, 'location_accuracy' => null]);
        // Historic fixture predating the pilot requirements, not a request to the recording endpoint.
        $old = (new VisitRecorder)->register($this->actor, $data);
        $before = $old->fresh()->getAttributes();
        $again = $this->recorder->register($this->actor, $data);
        $this->assertSame($old->id, $again->id);
        $this->assertSame($before, $old->fresh()->getAttributes());
        $this->reject(fn () => $this->recorder->register($this->actor, $data, [], $this->objective->id), 'objectiveId');
        $this->actingAs($this->actor)->get('/promoter/visits/'.$old->id)->assertOk()->assertDontSee('Objetivo general');
    }

    public function test_objective_snapshot_and_idempotent_replay_survive_catalog_deactivation(): void
    {
        $data = $this->data();
        $visit = $this->recorder->register($this->actor, $data, [], $this->objective->id);
        $this->objective->update(['name' => 'Descripción futura', 'is_active' => false]);
        $again = $this->recorder->register($this->actor, $data, [], $this->objective->id);
        $this->assertSame($visit->id, $again->id);
        $this->assertSame('Seguimiento comercial', $visit->objective->category_label);
        $this->assertSame(1, Visit::count());
    }

    public function test_location_is_explicit_one_shot_without_manual_inputs_or_automatic_permission_prompt(): void
    {
        $html = $this->actingAs($this->actor)->get('/promoter/visits/register')->assertOk()->getContent();
        $this->assertStringContainsString('id="capture-visit-location"', $html);
        $this->assertStringNotContainsString('x-init="capture()"', $html);
        $this->assertStringContainsString('getCurrentPosition', $html);
        $this->assertStringNotContainsString('watchPosition', $html);
        $this->assertStringNotContainsString('se registrará sin ella', $html);
        $this->assertStringContainsString('maximumAge:0', $html);
        $this->assertStringNotContainsString('name="latitude"', $html);
        $this->assertStringContainsString('Informe a Coordinación', $html);
    }

    public function test_planning_review_keeps_count_and_conflicts_without_a_second_open_selected_list(): void
    {
        $planner = new SchedulePlanner;
        $schedule = $planner->create($this->actor, 2026, 11);
        $c = Livewire::actingAs($this->actor)->test(Planning::class, ['period' => '2026-11'])->call('selectDay', '2026-11-03')->call('stagePharmacy', $this->pharmacy->id)->call('reviewBatch')->assertHasNoErrors()->assertSee('Revisar 1 visita válida')->assertDontSee('wire:key="staged-'.$this->pharmacy->id.'"', false);
        $this->assertSame(0, $schedule->activities()->count());
        $c->call('confirmBatch')->assertHasNoErrors();
        $this->assertSame(1, $schedule->activities()->count());
    }

    public function test_checklist_update_is_previewed_and_retires_combined_items_without_changing_history(): void
    {
        $oldNames = ['Push Money', 'Capacitación o revisión de plan de exhibición', 'Pedido generado', 'Entrega de material promocional', 'Entrega de muestra médica', 'Entrega de Plan paciente'];
        foreach ($oldNames as $i => $name) {
            ChecklistItem::create(['name' => $name, 'sort_order' => ($i + 1) * 10, 'is_active' => true]);
        }
        $old = (new VisitRecorder)->register($this->actor, $this->data());
        $answers = DB::table('visit_checklist')->orderBy('id')->get()->toJson();
        $oldVisit = $old->fresh()->getAttributes();
        $catalog = new OfficialVisitChecklist;
        $p = $catalog->preview();
        $this->assertSame($oldNames, ChecklistItem::query()->where('is_active', true)->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(array_values(array_diff($oldNames, ChecklistItem::OBSOLETE_NAMES)), ChecklistItem::active()->pluck('name')->all());
        $this->assertCount(3, array_filter($p['actions'], fn ($action) => $action['action'] === 'create'));
        $this->assertCount(2, array_filter($p['actions'], fn ($action) => $action['action'] === 'retire'));
        $directory = sys_get_temp_dir().'/raven-pilot-checklist-'.bin2hex(random_bytes(8));
        mkdir($directory);
        try {
            $result = $catalog->apply($p['sha256'], $directory);
            $this->assertFileExists($result['backup']);
            $this->assertSame(hash_file('sha256', $result['backup']), trim(file_get_contents($result['backup'].'.sha256')));
            $this->assertSame($answers, DB::table('visit_checklist')->orderBy('id')->get()->toJson());
            $this->assertSame($oldVisit, $old->fresh()->getAttributes());
            $this->assertSame(array_column(OfficialVisitChecklist::ITEMS, 0), ChecklistItem::active()->pluck('name')->all());
            $this->assertSame(0, $catalog->apply($catalog->preview()['sha256'], $directory)['changed']);
        } finally {
            foreach (glob($directory.'/*') as $file) {
                if (is_file($file) && dirname(realpath($file)) === realpath($directory)) {
                    unlink($file);
                }
            }rmdir($directory);
        }
    }
}
