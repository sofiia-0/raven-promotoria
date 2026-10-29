<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Models\Visit;
use App\Services\OfficialVisitChecklist;
use App\Services\VisitRecorder;
use Database\Seeders\VisitCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PromoterOfficialChecklistTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'raven-checklist-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') as $p) {
            if (is_file($p) && dirname(realpath($p)) === realpath($this->directory)) {
                unlink($p);
            }
        }rmdir($this->directory);
        parent::tearDown();
    }

    private function rows(string $table): string
    {
        return DB::table($table)->orderBy('id')->get()->toJson();
    }

    private function visit(array $answers = []): Visit
    {
        $actor = User::factory()->promoter()->create();
        $p = Pharmacy::factory()->create();
        PharmacyAssignment::factory()->create(['pharmacy_id' => $p->id, 'promoter_id' => $actor->id]);

        return (new VisitRecorder)->register($actor, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $p->id, 'checklist' => $answers]);
    }

    public function test_command_is_read_only_by_default_and_requires_application_arguments(): void
    {
        $this->assertSame(0, Artisan::call('raven:checklist'));
        $p = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(6, $p['actions']);
        $this->assertSame(array_column(OfficialVisitChecklist::ITEMS, 0), $p['official']);
        $this->assertSame(0, ChecklistItem::count());
        $this->assertSame(1, Artisan::call('raven:checklist', ['--apply' => true]));
        $this->assertSame(0, ChecklistItem::count());
    }

    public function test_fresh_seeder_uses_exact_official_labels_without_recommendation_and_is_idempotent(): void
    {
        (new VisitCatalogSeeder)->run();
        $before = $this->rows('checklist_items');
        (new VisitCatalogSeeder)->run();
        $this->assertSame($before, $this->rows('checklist_items'));
        $this->assertSame(['Push Money', 'Capacitación o revisión de plan de exhibición', 'Pedido generado', 'Entrega de material promocional', 'Entrega de muestra médica', 'Entrega de Plan paciente'], ChecklistItem::active()->pluck('name')->all());
        $this->assertDatabaseMissing('checklist_items', ['name' => 'Entrega de Plan de recomendación']);
    }

    public function test_retirement_preserves_historical_answers_labels_and_visits_and_new_visits_use_official_items(): void
    {
        $ids = [];
        foreach (OfficialVisitChecklist::RETIRED as $name) {
            $ids[] = ChecklistItem::factory()->create(['name' => $name])->id;
        }
        $visit = $this->visit([$ids[0] => true]);
        $beforeVisits = $this->rows('visits');
        $answers = $this->rows('visit_checklist');
        $labels = ChecklistItem::orderBy('id')->get(['id', 'name', 'description'])->toJson();
        $s = new OfficialVisitChecklist;
        $result = $s->apply($s->preview()['sha256'], $this->directory);
        $this->assertSame(13, $result['changed']);
        $this->assertSame(hash_file('sha256', $result['backup']), trim(file_get_contents($result['backup'].'.sha256')));
        $this->assertSame($answers, $this->rows('visit_checklist'));
        $this->assertSame($beforeVisits, $this->rows('visits'));
        $this->assertSame($labels, ChecklistItem::whereIn('id', $ids)->orderBy('id')->get(['id', 'name', 'description'])->toJson());
        $this->assertSame(0, ChecklistItem::whereIn('id', $ids)->where('is_active', true)->count());
        $this->actingAs($visit->promoter)->get('/promoter/visits/'.$visit->id)->assertOk()->assertSee('Exhibición de productos');
        $official = ChecklistItem::active()->get();
        $this->assertCount(6, $official);
        $new = $this->visit([$official[0]->id => true, $ids[0] => true, 999999 => true]);
        $this->assertCount(6, $new->checklist);
        $this->assertSame(1, $new->checklist->where('checked', true)->count());
        $this->assertSame($ids, $visit->fresh()->checklist->pluck('checklist_item_id')->all());
        $before = $this->rows('checklist_items');
        $this->assertSame(0, $s->apply($s->preview()['sha256'], $this->directory)['changed']);
        $this->assertSame($before, $this->rows('checklist_items'));
    }

    public function test_unlinked_normalized_label_reuses_id_and_inactive_official_item_is_reactivated(): void
    {
        $item = ChecklistItem::factory()->create(['name' => '  push  money  ', 'is_active' => false]);
        $s = new OfficialVisitChecklist;
        $s->apply($s->preview()['sha256'], $this->directory);
        $this->assertSame('Push Money', $item->fresh()->name);
        $this->assertTrue($item->fresh()->is_active);
        $this->assertSame(6, ChecklistItem::count());
    }

    public function test_duplicate_normalized_labels_block_all_changes(): void
    {
        ChecklistItem::factory()->create(['name' => 'Push Money']);
        ChecklistItem::factory()->create(['name' => 'push  money']);
        $before = $this->rows('checklist_items');
        $s = new OfficialVisitChecklist;
        $this->assertSame('duplicate_normalized_name', $s->preview()['conflicts'][0]['reason']);
        try {
            $s->apply($s->preview()['sha256'], $this->directory);
            $this->fail('Duplicate must require review.');
        } catch (RuntimeException) {
            $this->assertSame($before, $this->rows('checklist_items'));
        }
    }

    public function test_linked_noncanonical_labels_and_unknown_items_require_review(): void
    {
        ChecklistItem::factory()->create(['name' => 'push  money']);
        $this->visit();
        ChecklistItem::factory()->create(['name' => 'Actividad por revisar']);
        $s = new OfficialVisitChecklist;
        $plan = $s->preview();
        $this->assertContains('historical_label_requires_review', array_column($plan['conflicts'], 'reason'));
        $this->assertContains('unknown_active_item_requires_review', array_column($plan['conflicts'], 'reason'));
        $before = $this->rows('checklist_items');
        $answers = $this->rows('visit_checklist');
        try {
            $s->apply($plan['sha256'], $this->directory);
            $this->fail('Historical label must be preserved.');
        } catch (RuntimeException) {
            $this->assertSame($before, $this->rows('checklist_items'));
            $this->assertSame($answers, $this->rows('visit_checklist'));
        }
    }

    public function test_stale_plan_and_invalid_backup_path_prevent_updates(): void
    {
        $s = new OfficialVisitChecklist;
        $old = $s->preview();
        ChecklistItem::factory()->create(['name' => 'Push Money']);
        $before = $this->rows('checklist_items');
        foreach ([[$old['sha256'], $this->directory], [$s->preview()['sha256'], base_path('storage')], [$s->preview()['sha256'], $this->directory.'/missing']] as [$hash,$dir]) {
            try {
                $s->apply($hash, $dir);
                $this->fail('Unsafe update must fail.');
            } catch (RuntimeException) {
                $this->assertSame($before, $this->rows('checklist_items'));
            }
        }
    }

    public function test_seeder_cannot_silently_migrate_existing_catalog(): void
    {
        ChecklistItem::factory()->create(['name' => 'Precios visibles']);
        $before = $this->rows('checklist_items');
        try {
            (new VisitCatalogSeeder)->run();
            $this->fail('Catalog update requires review.');
        } catch (RuntimeException) {
            $this->assertSame($before, $this->rows('checklist_items'));
            $this->assertSame(0, DB::table('visit_reasons')->count());
        }
    }

    public function test_application_is_blocked_outside_local_and_testing(): void
    {
        $service = new OfficialVisitChecklist;
        $hash = $service->preview()['sha256'];
        $this->app->instance('env', 'production');
        try {
            $service->apply($hash, $this->directory);
            $this->fail('Production must be blocked.');
        } catch (RuntimeException) {
            $this->assertSame(0, ChecklistItem::count());
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
