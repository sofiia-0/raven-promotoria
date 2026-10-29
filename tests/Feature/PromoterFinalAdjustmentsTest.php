<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Promoter\PharmacyRequests\Index as Requests;
use App\Livewire\Promoter\Visits\Register;
use App\Livewire\Promoter\Visits\Show;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyRequest;
use App\Models\PromoterReasonCategory;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitObjective;
use App\Models\VisitReason;
use App\Services\OfficialVisitChecklist;
use App\Services\PharmacyRequestService;
use App\Services\PromoterReasonCategories;
use App\Services\PromoterReasonSynchronizer;
use App\Services\VisitContextRecorder;
use App\Services\VisitEvidenceStorage;
use App\Services\VisitRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PromoterFinalAdjustmentsTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $actor;

    private Pharmacy $pharmacy;

    private PromoterReasonSynchronizer $sync;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->promoter()->create();
        $this->pharmacy = Pharmacy::factory()->create();
        PharmacyAssignment::factory()->create(['promoter_id' => $this->actor->id, 'pharmacy_id' => $this->pharmacy->id]);
        $this->sync = new PromoterReasonSynchronizer;
        Storage::fake('visit_evidence');
    }

    private function synchronize(string $scope = 'all'): array
    {
        return $this->sync->apply($scope, $this->sync->preview($scope)['fingerprint']);
    }

    private function category(string $domain, string $code): PromoterReasonCategory
    {
        return PromoterReasonCategory::where('domain', $domain)->where('code', $code)->firstOrFail();
    }

    private function data(): array
    {
        return ['idempotency_key' => (string) Str::uuid(), 'pharmacy_id' => $this->pharmacy->id, 'status' => 'REALIZADA'];
    }

    private function reject(callable $call): void
    {
        try {
            $call();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    private function alta(array $extra = []): array
    {
        return [...['name' => 'Establecimiento ficticio', 'department' => 'Boaco', 'city' => 'Camoapa', 'address' => 'Dirección ficticia de prueba.', 'reason' => 'Explicación de prueba para revisión.'], ...$extra];
    }

    public function test_catalog_preview_is_readonly_and_sync_is_idempotent_without_changing_checklist(): void
    {
        (new OfficialVisitChecklist)->seedFresh();
        $before = DB::table('checklist_items')->orderBy('id')->get()->toJson();
        Artisan::call('raven:promoter-reasons');
        $this->assertSame(0, VisitReason::count());
        $this->assertSame(0, PromoterReasonCategory::count());
        $result = $this->synchronize();
        $this->assertSame(18, $result['created']);
        $this->assertSame(7, VisitReason::count());
        $this->assertSame(11, PromoterReasonCategory::count());
        $this->assertSame(11, PromoterReasonCategory::where('is_provisional', true)->count());
        $reasons = VisitReason::orderBy('id')->get()->toArray();
        $categories = PromoterReasonCategory::orderBy('id')->get()->toArray();
        $this->assertSame(0, $this->synchronize()['created']);
        $this->assertSame($reasons, VisitReason::orderBy('id')->get()->toArray());
        $this->assertSame($categories, PromoterReasonCategory::orderBy('id')->get()->toArray());
        $this->assertSame($before, DB::table('checklist_items')->orderBy('id')->get()->toJson());
    }

    public function test_sync_preserves_valid_historical_ids_spelling_and_inactive_options(): void
    {
        $old = VisitReason::create(['name' => '  establecimiento   CERRADO', 'is_active' => false, 'sort_order' => 321]);
        $before = $old->fresh()->getAttributes();
        $result = $this->synchronize('unperformed');
        $this->assertSame(6, $result['created']);
        $this->assertSame($before, $old->fresh()->getAttributes());
        $this->assertSame(7, VisitReason::count());
    }

    public function test_conflicts_and_stale_previews_abort_without_partial_inserts(): void
    {
        $preview = $this->sync->preview('unperformed');
        VisitReason::create(['name' => 'Establecimiento cerrado']);
        $this->reject(fn () => $this->sync->apply('unperformed', $preview['fingerprint']));
        $this->assertSame(1, VisitReason::count());
        VisitReason::create(['name' => 'Establecimiento  cerrado']);
        $plan = $this->sync->preview('unperformed');
        $this->assertNotEmpty($plan['conflicts']);
        $this->reject(fn () => $this->sync->apply('unperformed', $plan['fingerprint']));
        $this->assertSame(2, VisitReason::count());
    }

    public function test_no_realization_reasons_are_available_validated_and_preserved_in_history(): void
    {
        $this->synchronize('unperformed');
        $reason = VisitReason::first();
        $component = Livewire::actingAs($this->actor)->test(Register::class)->set('status', 'NO_REALIZADA')->assertSee($reason->name)->set('pharmacyId', (string) $this->pharmacy->id)->call('save')->assertHasErrors('reasonId');
        $component->set('reasonId', (string) $reason->id)->call('save')->assertHasNoErrors()->assertRedirect();
        $visit = Visit::first();
        $this->assertSame($reason->id, $visit->visit_reason_id);
        $this->assertSame(0, VisitObjective::count());
        $this->actingAs($this->actor)->get('/promoter/visits/'.$visit->id)->assertOk()->assertSee($reason->name);
    }

    public function test_an_inactive_no_realization_reason_remains_invalid(): void
    {
        $this->synchronize('unperformed');
        $reason = VisitReason::first();
        $reason->update(['is_active' => false]);
        $this->reject(fn () => (new VisitContextRecorder)->register($this->actor, [...$this->data(), 'status' => 'NO_REALIZADA', 'visit_reason_id' => $reason->id]));
        $this->assertSame(0, Visit::count());
    }

    public function test_objective_is_optional_and_does_not_rewrite_historical_visits(): void
    {
        $old = (new VisitRecorder)->register($this->actor, $this->data());
        $before = $old->fresh()->getAttributes();
        $this->synchronize();
        $visit = (new VisitContextRecorder)->register($this->actor, $this->data());
        $this->assertNull($visit->objective);
        $this->assertSame($before, $old->fresh()->getAttributes());
        $this->actingAs($this->actor)->get('/promoter/visits/'.$old->id)->assertOk()->assertDontSee('Objetivo general');
    }

    public function test_objective_has_a_separate_snapshot_and_does_not_duplicate_checklist(): void
    {
        $this->synchronize();
        (new OfficialVisitChecklist)->seedFresh();
        $category = $this->category('visit_objective', 'commercial_followup');
        $visit = (new VisitContextRecorder)->register($this->actor, $this->data(), [], $category->id);
        $this->assertSame($category->id, $visit->objective->promoter_reason_category_id);
        $this->assertSame(6, $visit->checklist()->count());
        $this->assertNull($visit->visit_reason_id);
        $category->update(['name' => 'Nombre aprobado posterior', 'is_provisional' => false]);
        $this->assertSame('Seguimiento comercial', $visit->objective->fresh()->category_label);
        $this->actingAs($this->actor)->get('/promoter/visits/'.$visit->id)->assertOk()->assertSee('Objetivo general')->assertSee('Seguimiento comercial')->assertSee('Provisional');
    }

    public function test_other_objective_requires_an_explanation_and_invalid_categories_are_rejected(): void
    {
        $this->synchronize();
        $other = $this->category('visit_objective', 'other');
        $this->reject(fn () => (new VisitContextRecorder)->register($this->actor, $this->data(), [], $other->id, 'Breve'));
        $this->reject(fn () => (new VisitContextRecorder)->register($this->actor, $this->data(), [], $this->category('request_alta', 'other')->id, 'Explicación de prueba.'));
        $this->reject(fn () => (new VisitContextRecorder)->register($this->actor, $this->data(), [], 999999));
        $other->update(['is_active' => false]);
        $this->reject(fn () => (new VisitContextRecorder)->register($this->actor, $this->data(), [], $other->id, 'Explicación de prueba.'));
        $this->assertSame(0, Visit::count());
    }

    public function test_livewire_other_objective_preserves_explanation_and_clears_stale_selection_on_change(): void
    {
        $this->synchronize();
        $other = $this->category('visit_objective', 'other');
        $c = Livewire::actingAs($this->actor)->test(Register::class)->set('pharmacyId', (string) $this->pharmacy->id)->set('objectiveId', (string) $other->id)->assertSee('Explique el objetivo')->set('objectiveExplanation', 'Detalle del objetivo de prueba.')->call('save')->assertHasNoErrors();
        $this->assertSame('Detalle del objetivo de prueba.', VisitObjective::first()->explanation);
        Livewire::actingAs($this->actor)->test(Register::class)->set('objectiveId', (string) $other->id)->set('objectiveExplanation', 'Detalle que no debe persistir.')->set('objectiveId', '')->assertSet('objectiveExplanation', '');
    }

    public function test_objective_replay_is_idempotent_and_cannot_append_to_an_old_visit(): void
    {
        $this->synchronize();
        $category = $this->category('visit_objective', 'commercial_followup');
        $data = $this->data();
        $recorder = new VisitContextRecorder;
        $visit = $recorder->register($this->actor, $data, [], $category->id);
        $again = $recorder->register($this->actor, $data, [], $category->id);
        $this->assertSame($visit->id, $again->id);
        $this->assertSame(1, VisitObjective::count());
        $oldData = $this->data();
        $old = $recorder->register($this->actor, $oldData);
        $this->reject(fn () => $recorder->register($this->actor, $oldData, [], $category->id));
        $this->assertNull($old->objective);
        $this->assertSame(1, VisitObjective::count());
    }

    public function test_objective_and_evidence_failure_rolls_back_all_visit_rows_but_keeps_pending_objects(): void
    {
        $this->synchronize();
        $data = $this->data();
        $file = (new VisitEvidenceStorage)->upload($this->actor, $data['idempotency_key'], UploadedFile::fake()->image('prueba.png'));
        Event::listen('eloquent.creating: '.VisitObjective::class, fn () => throw new \RuntimeException('simulated objective failure'));
        try {
            (new VisitContextRecorder)->register($this->actor, $data, [$file->id], $this->category('visit_objective', 'commercial_followup')->id);
            $this->fail('Expected rollback.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated objective failure', $e->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.VisitObjective::class);
        }
        $this->assertSame(0, Visit::count());
        $this->assertSame(0, VisitObjective::count());
        $this->assertSame('staged', $file->fresh()->status);
        $this->assertNull($file->fresh()->visit_id);
        Storage::disk('visit_evidence')->assertExists($file->path);
    }

    public function test_alta_and_baja_categories_are_separate_from_descriptive_text(): void
    {
        $this->synchronize();
        $s = new PharmacyRequestService;
        $alta = $s->submitAlta($this->actor, $this->alta(['reason_category_id' => $this->category('request_alta', 'new_establishment')->id]));
        $baja = $s->submitBaja($this->actor, $this->pharmacy->id, 'Explicación detallada de la baja.', $this->category('request_baja', 'establishment_closure')->id);
        $this->assertSame('new_establishment', $alta->data['reason_category']['code']);
        $this->assertSame('request_alta', $alta->data['reason_category']['domain']);
        $this->assertSame('request_baja', $baja->data['reason_category']['domain']);
        $this->assertSame('Explicación de prueba para revisión.', $alta->reason);
        $this->assertSame('pending', $baja->status);
        $this->assertTrue($this->pharmacy->fresh()->is_active);
    }

    public function test_request_other_invalid_foreign_domain_and_legacy_values_are_handled_safely(): void
    {
        $s = new PharmacyRequestService;
        $legacy = $s->submitAlta($this->actor, $this->alta());
        $this->synchronize();
        $this->reject(fn () => $s->submitAlta($this->actor, $this->alta(['reason_category_id' => $this->category('request_baja', 'other')->id])));
        $this->reject(fn () => (new PromoterReasonCategories)->resolve('request_alta', $this->category('request_alta', 'other')->id, 'Breve'));
        $before = $legacy->fresh()->getAttributes();
        $this->assertArrayNotHasKey('reason_category', $legacy->data);
        Livewire::actingAs($this->actor)->test(Requests::class)->assertSee($legacy->reason);
        $this->assertSame($before, $legacy->fresh()->getAttributes());
        $this->assertSame(1, PharmacyRequest::count());
    }

    public function test_request_forms_expose_searchable_categories_and_store_only_resolved_metadata(): void
    {
        $this->synchronize();
        $category = $this->category('request_alta', 'new_establishment');
        Livewire::actingAs($this->actor)->test(Requests::class)->call('openForm', 'alta')->assertSee('Categoría del motivo de alta')->assertSee('role="combobox"', false)->set('altaName', 'Establecimiento ficticio')->set('altaDepartment', 'Boaco')->set('altaCity', 'Camoapa')->set('altaAddress', 'Dirección ficticia de prueba.')->set('altaReasonCategoryId', (string) $category->id)->set('altaReason', 'Explicación detallada de prueba.')->call('submitAlta')->assertHasNoErrors();
        $this->assertSame($category->id, PharmacyRequest::first()->data['reason_category']['id']);
    }

    public function test_map_consent_is_per_authenticated_session_and_is_shared_by_register_and_detail(): void
    {
        $visit = (new VisitRecorder)->register($this->actor, [...$this->data(), 'latitude' => '12.12', 'longitude' => '-86.12']);
        $key = 'promoter.maps.authorized.'.$this->actor->id;
        Livewire::actingAs($this->actor)->test(Register::class)->assertViewHas('mapAuthorized', false)->call('authorizeVisitMap')->assertViewHas('mapAuthorized', true);
        $this->assertTrue(session($key));
        Livewire::actingAs($this->actor)->test(Show::class, ['visitId' => $visit->id])->assertViewHas('mapAuthorized', true)->call('revokeVisitMap')->assertViewHas('mapAuthorized', false);
        $this->assertFalse(session()->has($key));
        Livewire::actingAs($this->actor)->test(Register::class)->assertViewHas('mapAuthorized', false);
    }

    public function test_map_consent_does_not_leak_between_users_or_survive_session_clear(): void
    {
        Livewire::actingAs($this->actor)->test(Register::class)->call('authorizeVisitMap');
        $other = User::factory()->promoter()->create();
        Livewire::actingAs($other)->test(Register::class)->assertViewHas('mapAuthorized', false);
        session()->flush();
        Livewire::actingAs($this->actor)->test(Register::class)->assertViewHas('mapAuthorized', false);
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        Livewire::actingAs($admin)->test(Register::class)->assertForbidden();
    }

    public function test_checklist_terminology_is_presentation_only_and_external_maps_are_inert_before_consent(): void
    {
        (new OfficialVisitChecklist)->seedFresh();
        $visit = (new VisitRecorder)->register($this->actor, [...$this->data(), 'latitude' => '12.12', 'longitude' => '-86.12', 'location_accuracy' => 15, 'checklist' => []]);
        $before = $visit->checklist()->orderBy('id')->get()->toArray();
        $response = $this->actingAs($this->actor)->get('/promoter/visits/'.$visit->id)->assertOk()->assertSee('actividades registradas')->assertSee('Sin marcar')->assertDontSee('Cumplido')->assertDontSee('getCurrentPosition')->assertSee('durante esta sesión');
        $this->assertSame($before, $visit->checklist()->orderBy('id')->get()->toArray());
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//iframe[@src]')->length);
        $this->assertSame(1, $xpath->query('//template//iframe')->length);
    }

    public function test_sync_command_requires_a_private_backup_before_apply(): void
    {
        $plan = $this->sync->preview('unperformed');
        $this->assertSame(1, Artisan::call('raven:promoter-reasons', ['--apply' => true, '--expect' => $plan['fingerprint']]));
        $this->assertSame(0, VisitReason::count());
        $this->assertSame(1, Artisan::call('raven:promoter-reasons', ['--apply' => true, '--expect' => $plan['fingerprint'], '--backup' => base_path('storage')]));
        $this->assertSame(0, VisitReason::count());
    }

    public function test_explicit_private_catalog_backup_records_before_state_and_created_ids(): void
    {
        $directory = sys_get_temp_dir().'/raven-catalog-test-'.Str::uuid();
        mkdir($directory, 0700);
        try {
            $plan = $this->sync->preview('unperformed');
            $this->assertSame(0, Artisan::call('raven:promoter-reasons', ['--apply' => true, '--expect' => $plan['fingerprint'], '--backup' => $directory]));
            $files = glob($directory.'/*.json');
            $this->assertCount(2, $files);
            $backup = json_decode(file_get_contents(array_values(array_filter($files, fn ($path) => ! str_ends_with($path, '.result.json')))[0]), true, 512, JSON_THROW_ON_ERROR);
            $journal = json_decode(file_get_contents(array_values(array_filter($files, fn ($path) => str_ends_with($path, '.result.json')))[0]), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame([], $backup['before']['visit_reasons']);
            $this->assertCount(7, $journal['created_reasons']);
            $this->assertSame(7, VisitReason::count());
            $this->assertSame(0, $this->synchronize('unperformed')['created']);
        } finally {
            foreach (glob($directory.'/*.json') as $file) {
                unlink($file);
            }rmdir($directory);
        }
    }

    public function test_new_request_categories_are_required_in_service_and_livewire_when_catalog_is_available(): void
    {
        $this->synchronize();
        $service = new PharmacyRequestService;
        $this->reject(fn () => $service->submitAlta($this->actor, $this->alta()));
        $this->reject(fn () => $service->submitBaja($this->actor, $this->pharmacy->id, 'Justificación válida previa.'));
        Livewire::actingAs($this->actor)->test(Requests::class)->call('openForm', 'alta')
            ->set('altaName', 'Establecimiento ficticio')->set('altaDepartment', 'Boaco')->set('altaCity', 'Camoapa')
            ->set('altaAddress', 'Dirección ficticia de prueba.')->call('submitAlta')->assertHasErrors('altaReasonCategoryId');
        Livewire::actingAs($this->actor)->test(Requests::class)->call('openForm', 'baja')
            ->set('pharmacyId', (string) $this->pharmacy->id)->call('submitBaja')->assertHasErrors('bajaReasonCategoryId');
        $this->assertSame(0, PharmacyRequest::count());
    }

    public function test_optional_observations_keep_selected_reason_compatible_with_the_legacy_column(): void
    {
        $this->synchronize();
        $service = new PharmacyRequestService;
        $altaCategory = $this->category('request_alta', 'new_branch');
        $bajaCategory = $this->category('request_baja', 'coverage_change');
        $alta = $service->submitAlta($this->actor, $this->alta(['reason' => '', 'reason_category_id' => $altaCategory->id]));
        $baja = $service->submitBaja($this->actor, $this->pharmacy->id, '', $bajaCategory->id);
        $this->assertSame('Apertura de nueva sucursal', $alta->reason);
        $this->assertSame('Retiro de cobertura', $baja->reason);
        $this->assertSame($altaCategory->id, $alta->data['reason_category']['id']);
        $this->assertTrue($alta->data['reason_category']['is_provisional']);
        $this->assertSame('coverage_change', $baja->data['reason_category']['code']);
        $this->actingAs($this->actor)->get('/promoter/requests')->assertOk()->assertSee($alta->reason)->assertSee($baja->reason);
    }

    public function test_other_requests_require_explanation_without_relaxing_existing_text_limits(): void
    {
        $this->synchronize();
        $service = new PharmacyRequestService;
        $otherAlta = $this->category('request_alta', 'other');
        $otherBaja = $this->category('request_baja', 'other');
        $this->reject(fn () => $service->submitAlta($this->actor, $this->alta(['reason' => '', 'reason_category_id' => $otherAlta->id])));
        $this->reject(fn () => $service->submitBaja($this->actor, $this->pharmacy->id, 'Corto', $otherBaja->id));
        $this->reject(fn () => $service->submitAlta($this->actor, $this->alta(['reason' => str_repeat('a', 1501), 'reason_category_id' => $otherAlta->id])));
        $request = $service->submitBaja($this->actor, $this->pharmacy->id, 'Explicación de otro motivo.', $otherBaja->id);
        $this->assertSame('Explicación de otro motivo.', $request->reason);
        $this->assertSame('other', $request->data['reason_category']['code']);
    }

    public function test_other_unperformed_visit_requires_explanation_but_does_not_revalidate_historical_replays(): void
    {
        $this->synchronize();
        $reason = VisitReason::where('name', 'Otro motivo')->firstOrFail();
        $data = [...$this->data(), 'status' => 'NO_REALIZADA', 'visit_reason_id' => $reason->id];
        $recorder = new VisitContextRecorder;
        $this->reject(fn () => $recorder->register($this->actor, $data));
        $this->reject(fn () => $recorder->register($this->actor, [...$data, 'notes' => 'Corto']));
        $component = Livewire::actingAs($this->actor)->test(Register::class)->set('status', 'NO_REALIZADA')
            ->set('pharmacyId', (string) $this->pharmacy->id)->set('reasonId', (string) $reason->id)
            ->assertSee('Explique el motivo')->call('save')->assertHasErrors('notes');
        $component->set('notes', 'Explicación de otro motivo.')->call('save')->assertHasNoErrors();
        $visit = Visit::first();
        $this->assertSame('Explicación de otro motivo.', $visit->notes);
        $old = (new VisitRecorder)->register($this->actor, $data);
        $this->assertSame($old->id, $recorder->register($this->actor, $data)->id);
        $this->assertNull($old->fresh()->notes);
    }

    public function test_sync_never_renames_a_preexisting_category_or_its_historical_request_snapshot(): void
    {
        $category = PromoterReasonCategory::create(['domain' => 'request_baja', 'code' => 'coverage_change', 'name' => 'Cambio de cobertura', 'is_active' => true, 'is_provisional' => true]);
        $request = (new PharmacyRequestService)->submitBaja($this->actor, $this->pharmacy->id, 'Justificación histórica de prueba.', $category->id);
        $before = $request->fresh()->getAttributes();
        $categoryBefore = $category->fresh()->getAttributes();
        $this->synchronize('categories');
        $this->assertSame($before, $request->fresh()->getAttributes());
        $this->assertSame($categoryBefore, $category->fresh()->getAttributes());
        $this->assertSame('Cambio de cobertura', $request->data['reason_category']['label']);
        $this->assertSame(1, PromoterReasonCategory::where('domain', 'request_baja')->where('code', 'coverage_change')->count());
    }

    public function test_request_forms_accept_empty_observations_with_a_standard_category_and_keep_labels_clean(): void
    {
        $this->synchronize();
        Livewire::actingAs($this->actor)->test(Requests::class)->call('openForm', 'alta')
            ->assertDontSee('Opciones provisionales, pendientes de validación')
            ->set('altaName', 'Establecimiento ficticio')->set('altaDepartment', 'Boaco')->set('altaCity', 'Camoapa')
            ->set('altaAddress', 'Dirección ficticia de prueba.')->set('altaReasonCategoryId', (string) $this->category('request_alta', 'new_branch')->id)
            ->call('submitAlta')->assertHasNoErrors();
        Livewire::actingAs($this->actor)->test(Requests::class)->call('openForm', 'baja')
            ->set('pharmacyId', (string) $this->pharmacy->id)->set('bajaReasonCategoryId', (string) $this->category('request_baja', 'establishment_closure')->id)
            ->call('submitBaja')->assertHasNoErrors();
        $this->assertSame(2, PharmacyRequest::count());
    }
}
