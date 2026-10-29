<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Promoter\Visits\Register;
use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitEvidence;
use App\Services\AuditLogger;
use App\Services\EvidenceFileValidator;
use App\Services\OfficialVisitChecklist;
use App\Services\SchedulePlanner;
use App\Services\VisitEvidenceStorage;
use App\Services\VisitRecorder;
use App\Services\VisitWithEvidenceRecorder;
use App\Support\VisitMap;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class VisitEvidenceTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $actor;

    private Pharmacy $pharmacy;

    private VisitEvidenceStorage $files;

    private array $temporary = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 16:00:00', 'UTC'));
        Storage::fake('visit_evidence');
        $this->actor = User::factory()->promoter()->create();
        $this->pharmacy = Pharmacy::factory()->create();
        PharmacyAssignment::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'promoter_id' => $this->actor->id]);
        $this->files = new VisitEvidenceStorage;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporary as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }parent::tearDown();
    }

    private function data(?string $key = null): array
    {
        return ['idempotency_key' => $key ?? (string) Str::uuid(), 'pharmacy_id' => $this->pharmacy->id, 'status' => 'REALIZADA'];
    }

    private function image(string $name = 'foto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 120 + (int) preg_replace('/[^0-9]/', '', $name), 80);
    }

    private function pdf(string $extra = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('informe.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n".$extra."\n%%EOF\n");
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

    private function zip(string $name, array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'raven-test-');
        $this->temporary[] = $path;
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach ($entries as $n => $content) {
            $zip->addFromString($n, $content);
        }$zip->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    private function office(string $extension, array $extra = []): UploadedFile
    {
        $root = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'][$extension];
        $type = ['docx' => 'wordprocessingml.document.main+xml', 'xlsx' => 'spreadsheetml.sheet.main+xml', 'pptx' => 'presentationml.presentation.main+xml'][$extension];

        return $this->zip('documento.'.$extension, array_merge(['[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/'.$root.'" ContentType="application/vnd.openxmlformats-officedocument.'.$type.'"/></Types>', '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>', $root => '<document xmlns="urn:raven-test"/>'], $extra));
    }

    private function attach(int $count = 1): array
    {
        $data = $this->data();
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $files[] = $this->files->upload($this->actor, $data['idempotency_key'], $this->image('foto'.$i.'.jpg'));
        }$visit = (new VisitWithEvidenceRecorder)->register($this->actor, $data, array_map(fn ($f) => $f->id, $files));

        return [$visit, $files, $data];
    }

    public function test_zero_one_and_several_evidences_work_and_paths_are_private(): void
    {
        $zero = (new VisitWithEvidenceRecorder)->register($this->actor, $this->data());
        $this->assertSame(0, $zero->evidences()->count());
        foreach ([1, 3] as $count) {
            [$visit,$files] = $this->attach($count);
            $this->assertSame($count, $visit->evidences()->count());
            foreach ($files as $file) {
                $stored = $file->fresh();
                $this->assertSame('attached', $stored->status);
                Storage::disk('visit_evidence')->assertExists($stored->path);
                $this->assertStringNotContainsString('foto', $stored->path);
                $this->assertArrayNotHasKey('path', $stored->toArray());
                $this->assertArrayNotHasKey('disk', $stored->toArray());
            }
        }
    }

    public function test_planned_registration_with_files_preserves_source_activity_and_official_checklist(): void
    {
        (new OfficialVisitChecklist)->seedFresh();
        $planner = new SchedulePlanner;
        $s = $planner->create($this->actor, 2026, 10);
        $a = $planner->addActivity($this->actor, $s->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-10-15', 'pharmacy_id' => $this->pharmacy->id]);
        $planner->submit($this->actor, $s->id);
        $before = $a->fresh()->getAttributes();
        $data = ['idempotency_key' => (string) Str::uuid(), 'planned_activity_id' => $a->id, 'status' => 'REALIZADA', 'checklist' => [1 => true]];
        $file = $this->files->upload($this->actor, $data['idempotency_key'], $this->image());
        $visit = (new VisitWithEvidenceRecorder)->register($this->actor, $data, [$file->id]);
        $this->assertSame($a->id, $visit->planned_activity_id);
        $this->assertCount(6, $visit->checklist);
        $this->assertSame($before, $a->fresh()->getAttributes());
    }

    public function test_upload_endpoint_is_authenticated_and_does_not_return_storage_paths(): void
    {
        $this->postJson(route('promoter.visits.evidence.upload'), [])->assertUnauthorized();
        $this->actingAs($this->actor)->postJson(route('promoter.visits.evidence.upload'), ['batch_key' => (string) Str::uuid(), 'file' => $this->image()])->assertCreated()->assertJsonStructure(['id', 'name', 'size', 'mime'])->assertJsonMissingPath('path')->assertJsonMissingPath('disk');
        $this->assertSame(0, Visit::count());
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $this->actingAs($admin)->postJson(route('promoter.visits.evidence.upload'), ['batch_key' => (string) Str::uuid(), 'file' => $this->image()])->assertForbidden();
    }

    public function test_download_and_preview_require_the_visit_owner_even_after_assignment_changes(): void
    {
        [$visit,$files] = $this->attach();
        $f = $files[0];
        $download = route('promoter.visits.evidence.download', ['visit' => $visit->id, 'evidence' => $f->id]);
        $preview = route('promoter.visits.evidence.preview', ['visit' => $visit->id, 'evidence' => $f->id]);
        $this->actingAs($this->actor)->get($download)->assertOk()->assertDownload($f->original_name)->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($preview)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $other = User::factory()->promoter()->create();
        $this->pharmacy->assignments()->update(['ended_at' => now()]);
        PharmacyAssignment::factory()->create(['promoter_id' => $other->id, 'pharmacy_id' => $this->pharmacy->id]);
        $this->get($download)->assertOk();
        $this->actingAs($other)->get($download)->assertNotFound();
        $this->get($preview)->assertNotFound();
        $this->get('/promoter/visits/'.$visit->id)->assertNotFound();
    }

    public function test_evidence_ids_cannot_be_reused_by_other_actors_batches_or_visits(): void
    {
        $data = $this->data();
        $file = $this->files->upload($this->actor, $data['idempotency_key'], $this->image());
        $other = User::factory()->promoter()->create();
        $this->reject(fn () => $this->files->staged($other, $data['idempotency_key'], [$file->id]));
        $this->reject(fn () => (new VisitWithEvidenceRecorder)->register($this->actor, $this->data(), [$file->id]));
        $this->assertSame(0, Visit::count());
        $visit = (new VisitWithEvidenceRecorder)->register($this->actor, $data, [$file->id]);
        $this->reject(fn () => (new VisitWithEvidenceRecorder)->register($this->actor, $this->data(), [$file->id]));
        $this->assertSame(1, Visit::count());
        $this->assertSame($visit->id, $file->fresh()->visit_id);
    }

    public function test_replays_are_idempotent_and_historical_visits_cannot_receive_new_attachments(): void
    {
        [$visit,$files,$data] = $this->attach();
        $again = (new VisitWithEvidenceRecorder)->register($this->actor, $data, [$files[0]->id]);
        $this->assertSame($visit->id, $again->id);
        $this->assertSame(1, $visit->evidences()->count());
        $new = $this->files->upload($this->actor, $data['idempotency_key'], $this->pdf());
        $before = $visit->fresh()->getAttributes();
        $this->reject(fn () => (new VisitWithEvidenceRecorder)->register($this->actor, $data, [$new->id]));
        $this->assertSame($before, $visit->fresh()->getAttributes());
        $this->assertSame('staged', $new->fresh()->status);
    }

    public function test_failed_registration_keeps_retriable_staged_files_and_creates_no_visit(): void
    {
        $data = $this->data();
        $file = $this->files->upload($this->actor, $data['idempotency_key'], $this->image());
        $this->pharmacy->update(['is_active' => false]);
        $this->reject(fn () => (new VisitWithEvidenceRecorder)->register($this->actor, $data, [$file->id]));
        $this->assertSame(0, Visit::count());
        $this->assertSame('staged', $file->fresh()->status);
        Storage::disk('visit_evidence')->assertExists($file->path);
    }

    public function test_missing_or_tampered_objects_prevent_registration_without_orphan_visit_rows(): void
    {
        $data = $this->data();
        $file = $this->files->upload($this->actor, $data['idempotency_key'], $this->image());
        Storage::disk('visit_evidence')->put($file->path, 'tampered');
        $this->reject(fn () => (new VisitWithEvidenceRecorder)->register($this->actor, $data, [$file->id]));
        $this->assertSame(0, Visit::count());
        $this->assertNull($file->fresh()->visit_id);
    }

    public function test_livewire_accepts_and_removes_only_its_own_staged_files(): void
    {
        $c = Livewire::actingAs($this->actor)->test(Register::class);
        $key = $c->get('idempotencyKey');
        $file = $this->files->upload($this->actor, $key, $this->image());
        $c->call('addEvidenceUpload', $file->id)->assertSet('evidenceIds', [$file->id])->assertSee($file->original_name)->call('removeEvidenceUpload', $file->id)->assertSet('evidenceIds', []);
        $this->assertNull($file->fresh());
        Storage::disk('visit_evidence')->assertMissing($file->path);
    }

    public function test_count_size_and_identical_file_limits_are_enforced_on_the_server(): void
    {
        $key = (string) Str::uuid();
        $first = $this->files->upload($this->actor, $key, $this->image());
        $this->reject(fn () => $this->files->upload($this->actor, $key, $this->image()));
        config(['visit_evidence.max_file_bytes' => 100]);
        $this->reject(fn () => $this->files->upload($this->actor, (string) Str::uuid(), $this->image()));
        config(['visit_evidence.max_file_bytes' => 10485760, 'visit_evidence.max_files' => 1]);
        $this->reject(fn () => $this->files->upload($this->actor, $key, $this->pdf()));
        config(['visit_evidence.max_files' => 10, 'visit_evidence.max_visit_bytes' => 100]);
        $this->reject(fn () => $this->files->staged($this->actor, $key, [$first->id]));
        $this->assertSame(0, Visit::count());
    }

    public function test_valid_png_webp_pdf_office_and_flat_zip_are_recognized_by_content(): void
    {
        $validator = new EvidenceFileValidator;
        foreach (['png', 'webp'] as $ext) {
            $file = $this->image('foto.'.$ext);
            $this->assertSame('image/'.$ext, $validator->inspect($file)['mime']);
        }
        $this->assertSame('application/pdf', $validator->inspect($this->pdf())['mime']);
        foreach (['docx', 'xlsx', 'pptx'] as $ext) {
            $this->assertSame($ext, $validator->inspect($this->office($ext))['extension']);
        }
        $zip = $this->zip('evidencias.zip', ['nota.txt' => 'Documento de prueba sin contenido activo.']);
        $this->assertSame('application/zip', $validator->inspect($zip)['mime']);
    }

    public function test_scripts_executables_forged_types_and_active_pdf_are_rejected(): void
    {
        $validator = new EvidenceFileValidator;
        foreach ([UploadedFile::fake()->createWithContent('foto.jpg', '<?php echo 1;'), UploadedFile::fake()->createWithContent('archivo.html', '<html>prueba</html>'), UploadedFile::fake()->createWithContent('archivo.exe.jpg', 'MZprogram'), $this->pdf('/JavaScript (alert(1))')] as $file) {
            $this->reject(fn () => $validator->inspect($file));
        }
    }

    public function test_zip_traversal_scripts_nested_archives_and_expansion_abuse_are_rejected(): void
    {
        $validator = new EvidenceFileValidator;
        foreach ([['../nota.txt' => 'Texto.'], ['script.php' => '<?php echo 1;'], ['archivo.zip' => "PK\x03\x04resto"], ['grande.txt' => str_repeat('A', 200000)]] as $entries) {
            $this->reject(fn () => $validator->inspect($this->zip('evidencias.zip', $entries)));
        }
    }

    public function test_office_macros_external_relationships_and_entities_are_rejected(): void
    {
        $validator = new EvidenceFileValidator;
        foreach ([['word/vbaProject.bin' => 'macro'], ['word/_rels/document.xml.rels' => '<Relationships><Relationship TargetMode="External" Target="https://example.invalid/"/></Relationships>'], ['word/document.xml' => '<!DOCTYPE test [<!ENTITY e SYSTEM "file:///not-read">]><test>&e;</test>']] as $extra) {
            $this->reject(fn () => $validator->inspect($this->office('docx', $extra)));
        }
    }

    public function test_expired_pending_cleanup_is_explicit_and_never_deletes_attached_files(): void
    {
        [$visit,$attached] = $this->attach();
        $pending = $this->files->upload($this->actor, (string) Str::uuid(), $this->pdf());
        $pending->update(['created_at' => now()->subDays(2)]);
        Artisan::call('raven:evidence-prune');
        $this->assertNotNull($pending->fresh());
        Artisan::call('raven:evidence-prune', ['--apply' => true]);
        $this->assertNull($pending->fresh());
        Storage::disk('visit_evidence')->assertMissing($pending->path);
        $this->assertNotNull($attached[0]->fresh());
        $this->assertSame(1, $visit->evidences()->count());
    }

    public function test_map_is_inert_until_activation_and_validates_coordinates_without_recapturing(): void
    {
        $visit = (new VisitRecorder)->register($this->actor, [...$this->data(), 'latitude' => '12.1234567', 'longitude' => '-86.1234567', 'location_accuracy' => 25]);
        $before = $visit->fresh()->getAttributes();
        $response = $this->actingAs($this->actor)->get('/promoter/visits/'.$visit->id)->assertOk()->assertSee('Autorizar mapas de OpenStreetMap')->assertSee('dirección IP')->assertSee('± 25 m');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $x = new \DOMXPath($dom);
        $this->assertSame(1, $x->query('//template[@x-if="authorized && valid()"]//iframe')->length);
        $this->assertSame(0, $x->query('//iframe[not(ancestor::template)]')->length);
        $this->assertStringNotContainsString('getCurrentPosition', $response->getContent());
        $this->assertSame($before, $visit->fresh()->getAttributes());
        foreach ([[null, null], [91, 1], [1, 181], ['NaN', 0]] as [$lat,$lon]) {
            $v = new Visit(['latitude' => $lat, 'longitude' => $lon]);
            $this->assertNull(VisitMap::url($v));
        }
        $this->assertNotNull(VisitMap::url(new Visit(['latitude' => 0, 'longitude' => 0])));
    }

    public function test_without_coordinates_no_map_is_loaded_and_denied_location_does_not_prevent_recording(): void
    {
        $visit = (new VisitWithEvidenceRecorder)->register($this->actor, $this->data());
        $this->actingAs($this->actor)->get('/promoter/visits/'.$visit->id)->assertOk()->assertDontSee('Autorizar mapas de OpenStreetMap')->assertSee('No registrada');
        $this->assertFalse($visit->hasLocation());
    }

    public function test_failed_storage_write_leaves_no_untracked_object_or_pending_row(): void
    {
        $broken = \Mockery::mock(FilesystemAdapter::class);
        $broken->shouldReceive('putFileAs')->once()->andThrow(new \RuntimeException('simulated write failure'));
        $broken->shouldReceive('exists')->andReturn(false);
        Storage::shouldReceive('disk')->with('visit_evidence')->andReturn($broken);
        $this->reject(fn () => $this->files->upload($this->actor, (string) Str::uuid(), $this->image()));
        $this->assertSame(0, VisitEvidence::count());
        $this->assertSame(0, Visit::count());
    }

    public function test_final_transaction_failure_rolls_back_visit_and_preserves_pending_files_for_retry(): void
    {
        $data = $this->data();
        $file = $this->files->upload($this->actor, $data['idempotency_key'], $this->image());
        $audit = \Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('simulated audit failure'));
        $before = AuditLog::count();
        try {
            (new VisitWithEvidenceRecorder(audit: $audit))->register($this->actor, $data, [$file->id]);
            $this->fail('Expected rollback.');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated audit failure', $e->getMessage());
        }
        $this->assertSame(0, Visit::count());
        $this->assertSame($before, AuditLog::count());
        $this->assertSame('staged', $file->fresh()->status);
        $this->assertNull($file->fresh()->visit_id);
        $this->assertTrue($this->files->intact($file));
        $visit = (new VisitWithEvidenceRecorder)->register($this->actor, $data, [$file->id]);
        $this->assertSame(1, $visit->evidences()->count());
    }

    public function test_documents_are_downloads_and_cannot_be_previewed_or_linked_to_another_visit(): void
    {
        $data = $this->data();
        $file = $this->files->upload($this->actor, $data['idempotency_key'], $this->pdf());
        $visit = (new VisitWithEvidenceRecorder)->register($this->actor, $data, [$file->id]);
        $this->actingAs($this->actor)->get(route('promoter.visits.evidence.preview', ['visit' => $visit->id, 'evidence' => $file->id]))->assertNotFound();
        $this->get(route('promoter.visits.evidence.download', ['visit' => $visit->id, 'evidence' => $file->id]))->assertOk()->assertDownload('informe.pdf');
        $other = (new VisitRecorder)->register($this->actor, $this->data());
        $this->get(route('promoter.visits.evidence.download', ['visit' => $other->id, 'evidence' => $file->id]))->assertNotFound();
    }

    public function test_encoded_active_content_cannot_bypass_document_checks(): void
    {
        $validator = new EvidenceFileValidator;
        $this->reject(fn () => $validator->inspect($this->pdf('/J#61vaScript (alert(1))')));
        $xml = '<Relationships><Relationship TargetMode="Ex&#116;ernal" Target="https://example.invalid/"/></Relationships>';
        $this->reject(fn () => $validator->inspect($this->office('docx', ['word/_rels/document.xml.rels' => $xml])));
        $utf16 = "\xff\xfe".mb_convert_encoding('<?xml version="1.0" encoding="UTF-16"?>'.$xml, 'UTF-16LE', 'UTF-8');
        $this->reject(fn () => $validator->inspect($this->office('docx', ['word/_rels/document.xml.rels' => $utf16])));
        $doctype = "\xff\xfe".mb_convert_encoding('<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE test [<!ENTITY e "internal">]><test>&e;</test>', 'UTF-16LE', 'UTF-8');
        $this->reject(fn () => $validator->inspect($this->office('docx', ['word/document.xml' => $doctype])));
    }
}
