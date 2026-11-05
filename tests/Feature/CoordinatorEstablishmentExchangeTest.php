<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Establishments\Exchange;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Models\Visit;
use App\Services\EstablishmentExchange;
use App\Services\EstablishmentWorkbook;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;
use ZipArchive;

class CoordinatorEstablishmentExchangeTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function file(array $rows): string
    {
        $path = sys_get_temp_dir().'/raven-test-'.Str::uuid().'.xlsx';
        $this->paths[] = $path;
        app(EstablishmentWorkbook::class)->write($path, $rows);

        return $path;
    }

    private function row(Pharmacy $pharmacy, array $changes = []): array
    {
        return array_merge($pharmacy->only(EstablishmentWorkbook::COLUMNS), $changes);
    }

    public function test_filtered_exports_and_formula_like_names_are_safe_text(): void
    {
        $actor = User::factory()->coordinator()->create();
        Pharmacy::factory()->create(['name' => '=1+1', 'department' => 'Managua']);
        Pharmacy::factory()->create(['department' => 'León', 'is_active' => false]);
        $service = app(EstablishmentExchange::class);
        $filtered = $service->export($actor, ['department' => 'Managua']);
        $complete = $service->export($actor, [], true);
        $this->paths = [$filtered, $complete];

        $rows = app(EstablishmentWorkbook::class)->read($filtered);
        $this->assertCount(1, $rows);
        $this->assertSame('=1+1', $rows[0]['values']['name']);
        $this->assertSame(EstablishmentWorkbook::COLUMNS, array_keys($rows[0]['values']));
        $this->assertCount(2, app(EstablishmentWorkbook::class)->read($complete));
    }

    public function test_preview_apply_and_reimport_are_idempotent_and_preserve_history(): void
    {
        $actor = User::factory()->coordinator()->create();
        $person = User::factory()->promoter()->create();
        $record = Pharmacy::factory()->create(['source_reference' => 'TEST-ONE', 'name' => 'Marca · Sucursal 2', 'neighborhood' => 'Barrio histórico']);
        $other = Pharmacy::factory()->create(['source_reference' => 'TEST-TWO']);
        $assignment = PharmacyAssignment::factory()->create(['promoter_id' => $person->id, 'pharmacy_id' => $record->id]);
        $visit = Visit::create(['idempotency_key' => (string) Str::uuid(), 'promoter_id' => $person->id, 'pharmacy_id' => $record->id, 'status' => 'REALIZADA', 'visited_at' => now()]);
        $before = $visit->fresh()->getRawOriginal();
        $file = $this->file([$this->row($record, ['phone' => '2222-3333']), $this->row($other), ['source_reference' => 'TEST-NEW', 'name' => 'Nuevo local', 'department' => 'Managua', 'city' => 'Managua', 'address' => 'Dirección ficticia', 'is_active' => 1]]);
        $service = app(EstablishmentExchange::class);

        $batch = $service->preview($actor, $file);
        $this->assertSame(['modified', 'unchanged', 'new'], array_column($batch->manifest, 'classification'));
        $this->assertDatabaseCount('pharmacies', 2);
        $service->apply($actor, $batch->id, $batch->manifest_sha256);
        $service->apply($actor, $batch->id, $batch->manifest_sha256);

        $this->assertSame(['unchanged', 'unchanged', 'unchanged'], array_column($service->preview($actor, $file)->manifest, 'classification'));
        $this->assertDatabaseCount('pharmacies', 3);
        $this->assertSame('2222-3333', $record->fresh()->phone);
        $this->assertSame('Marca · Sucursal 2', $record->fresh()->name);
        $this->assertSame('Barrio histórico', $record->fresh()->neighborhood);
        $this->assertNull($assignment->fresh()->ended_at);
        $this->assertSame($before, $visit->fresh()->getRawOriginal());
    }

    public function test_concurrent_changes_roll_back_all_rows(): void
    {
        $actor = User::factory()->coordinator()->create();
        $first = Pharmacy::factory()->create(['phone' => 'Original A']);
        $second = Pharmacy::factory()->create(['phone' => 'Original B']);
        $service = app(EstablishmentExchange::class);
        $batch = $service->preview($actor, $this->file([$this->row($first, ['phone' => 'Propuesta A']), $this->row($second, ['phone' => 'Propuesta B'])]));
        $second->update(['phone' => 'Cambio posterior']);
        try {
            $service->apply($actor, $batch->id, $batch->manifest_sha256);
            $this->fail('Expected conflict.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('workbook', $error->errors());
        }
        $this->assertSame('Original A', $first->fresh()->phone);
        $this->assertSame('Cambio posterior', $second->fresh()->phone);
        $this->assertDatabaseCount('pharmacy_changes', 0);
    }

    public function test_duplicate_rows_and_invalid_geography_block_import(): void
    {
        $actor = User::factory()->coordinator()->create();
        $record = Pharmacy::factory()->create(['source_reference' => 'TEST-ORIGINAL']);
        $file = $this->file([$this->row($record), $this->row($record), $this->row($record, ['source_reference' => 'FORGED']), ['source_reference' => 'NEW-INVALID', 'name' => 'Nuevo', 'department' => 'León', 'city' => 'Managua', 'address' => 'Dirección ficticia', 'is_active' => 1]]);
        $batch = app(EstablishmentExchange::class)->preview($actor, $file);
        $this->assertSame(['unchanged', 'error', 'error', 'error'], array_column($batch->manifest, 'classification'));
        $this->expectException(ValidationException::class);
        app(EstablishmentExchange::class)->apply($actor, $batch->id, $batch->manifest_sha256);
    }

    public function test_formula_xml_is_rejected_before_import(): void
    {
        $path = $this->file([['name' => 'Texto']]);
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('</sheetData>', '<row r="3"><c r="A3"><f>1+1</f><v>2</v></c></row></sheetData>', $xml));
        $zip->close();
        $this->expectException(ValidationException::class);
        app(EstablishmentWorkbook::class)->read($path);
    }

    public function test_only_coordinators_can_access_exchange(): void
    {
        foreach ([UserRole::PROMOTER, UserRole::ADMIN] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('coordinator.establishments.exchange'))->assertForbidden();
            Livewire::actingAs($actor)->test(Exchange::class)->assertForbidden();
        }
        $actor = User::factory()->coordinator()->create();
        Livewire::actingAs($actor)->test(Exchange::class)->assertSee('Generar vista previa');
        $this->actingAs($actor)->get(route('coordinator.establishments.exchange'))->assertOk();
    }

    public function test_manifest_ownership_and_confirmation_hash_are_enforced(): void
    {
        $actor = User::factory()->coordinator()->create();
        $other = User::factory()->coordinator()->create();
        $record = Pharmacy::factory()->create();
        $service = app(EstablishmentExchange::class);
        $batch = $service->preview($actor, $this->file([$this->row($record)]));
        try {
            $service->apply($actor, $batch->id, str_repeat('0', 64));
            $this->fail('Expected confirmation mismatch.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('workbook', $error->errors());
        }
        $this->expectException(ModelNotFoundException::class);
        $service->apply($other, $batch->id, $batch->manifest_sha256);
    }

    public function test_duplicate_locations_are_reviewed_and_different_branches_are_preserved(): void
    {
        $actor = User::factory()->coordinator()->create();
        Pharmacy::factory()->create(['name' => 'Marca', 'department' => 'Managua', 'city' => 'Managua', 'address' => 'Local norte']);
        $base = ['name' => 'Marca', 'department' => 'Managua', 'city' => 'Managua', 'is_active' => 1];
        $file = $this->file([array_merge($base, ['source_reference' => 'NEW-ONE', 'address' => 'Local norte']), array_merge($base, ['source_reference' => 'NEW-TWO', 'address' => 'Local sur'])]);
        $batch = app(EstablishmentExchange::class)->preview($actor, $file);

        $this->assertSame(['error', 'new'], array_column($batch->manifest, 'classification'));
        $this->assertDatabaseCount('pharmacies', 1);
    }

    public function test_external_links_and_unsafe_archive_paths_are_rejected(): void
    {
        foreach (['external', 'traversal'] as $kind) {
            $path = $this->file([['name' => 'Texto']]);
            $zip = new ZipArchive;
            $zip->open($path);
            if ($kind === 'external') {
                $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
                $zip->addFromString('xl/_rels/workbook.xml.rels', str_replace('</Relationships>', '<Relationship Id="rId99" Type="http://example.test/link" Target="https://example.test" TargetMode="External"/></Relationships>', $rels));
            } else {
                $zip->addFromString('../outside.xml', '<test/>');
            }
            $zip->close();
            try {
                app(EstablishmentWorkbook::class)->read($path);
                $this->fail('Expected unsafe workbook rejection.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('workbook', $error->errors());
            }
        }
    }

    public function test_matching_new_rows_in_the_same_file_require_review(): void
    {
        $actor = User::factory()->coordinator()->create();
        $row = ['name' => 'Marca', 'department' => 'Managua', 'city' => 'Managua', 'address' => 'Local norte', 'is_active' => 1];
        $file = $this->file([array_merge($row, ['source_reference' => 'NEW-A']), array_merge($row, ['source_reference' => 'NEW-B'])]);
        $batch = app(EstablishmentExchange::class)->preview($actor, $file);

        $this->assertSame(['new', 'error'], array_column($batch->manifest, 'classification'));
        $this->assertDatabaseCount('pharmacies', 0);
    }

    public function test_expired_previews_and_historical_neighborhood_changes_are_blocked(): void
    {
        $this->freezeTime();
        $actor = User::factory()->coordinator()->create();
        $record = Pharmacy::factory()->create(['neighborhood' => 'Barrio conservado']);
        $service = app(EstablishmentExchange::class);
        $invalid = $service->preview($actor, $this->file([$this->row($record, ['neighborhood' => 'Otro barrio'])]));
        $this->assertSame('error', $invalid->manifest[0]['classification']);
        $batch = $service->preview($actor, $this->file([$this->row($record)]));
        $this->travel(49)->hours();

        $this->expectException(ValidationException::class);
        $service->apply($actor, $batch->id, $batch->manifest_sha256);
    }

    public function test_export_cleanup_defaults_to_simulation_and_keeps_recent_files(): void
    {
        $directory = storage_path('app/private/establishment-exchange');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $old = $directory.'/'.Str::uuid().'.xlsx';
        $recent = $directory.'/'.Str::uuid().'.xlsx';
        $this->paths = [$old, $recent];
        file_put_contents($old, 'old synthetic export');
        file_put_contents($recent, 'recent synthetic export');
        touch($old, now()->subHours(25)->getTimestamp());

        $this->artisan('raven:establishment-exports:prune')->assertSuccessful();
        $this->assertFileExists($old);
        $this->artisan('raven:establishment-exports:prune --apply')->assertSuccessful();
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($recent);
    }

    public function test_real_upload_preview_confirmation_and_download_through_livewire(): void
    {
        $this->freezeTime();
        Storage::fake('local');
        config(['livewire.temporary_file_upload.disk' => 'local']);
        $actor = User::factory()->coordinator()->create();
        $record = Pharmacy::factory()->create(['phone' => '1111-2222']);
        $path = $this->file([$this->row($record, ['phone' => '3333-4444'])]);
        $upload = UploadedFile::fake()->createWithContent('fichero.xlsx', file_get_contents($path));

        $screen = Livewire::actingAs($actor)->test(Exchange::class)->set('workbook', $upload)->call('preview')->assertHasNoErrors()->assertSee('Vista previa sin aplicar');
        $this->assertSame('1111-2222', $record->fresh()->phone);
        $screen->call('apply')->assertHasNoErrors()->assertSee('Importación aplicada');
        $this->assertSame('3333-4444', $record->fresh()->phone);
        $screen->call('exportComplete')->assertFileDownloaded('raven-establecimientos-'.now()->format('Ymd-His').'.xlsx');
    }
}
