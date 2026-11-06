<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Assignments\Index as AssignmentsIndex;
use App\Livewire\Coordinator\Establishments\Index as EstablishmentsIndex;
use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Services\EstablishmentExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class CoordinatorEstablishmentMasterTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    public function test_master_preserves_one_row_per_establishment_and_distinguishes_coverage_states(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(12, 0));
        $actor = User::factory()->coordinator()->create();
        $pharmacy = Pharmacy::factory()->create(['name' => '=1+1', 'source_reference' => 'MASTER-1', 'department' => 'Managua']);
        $unassigned = Pharmacy::factory()->create(['department' => 'Managua']);
        $inactive = Pharmacy::factory()->create(['department' => 'León', 'is_active' => false]);
        foreach (['Ana', 'Beatriz'] as $name) {
            $this->relation($pharmacy, $name);
        }
        $this->relation($pharmacy, 'Camila', ['assignment_kind' => 'coverage',
            'coverage_starts_on' => '2026-11-06', 'coverage_ends_on' => '2026-11-06']);
        $this->relation($pharmacy, 'Diana', ['assignment_kind' => 'coverage',
            'coverage_starts_on' => '2026-11-01', 'coverage_ends_on' => '2026-11-05']);
        $this->relation($pharmacy, 'Elena', ['assignment_kind' => 'coverage',
            'coverage_starts_on' => '2026-11-07', 'coverage_ends_on' => '2026-11-08']);
        $this->relation($inactive, 'Fabiola', ['ended_at' => now()->subDay()]);
        $service = app(EstablishmentExchange::class);

        $filtered = $this->read($service->exportMaster($actor, ['department' => 'Managua']));
        $complete = $this->read($service->exportMaster($actor, ['department' => 'Managua'], true));

        $this->assertSame(['Establecimientos', 'Responsables y coberturas', 'Alcance'], array_keys($filtered));
        $this->assertSame([$pharmacy->id, $unassigned->id], array_column(array_slice($filtered['Establecimientos'], 1), 0));
        $row = $filtered['Establecimientos'][1];
        $this->assertSame(['MASTER-1', '=1+1'], array_slice($row, 1, 2));
        $this->assertSame(['Ana; Beatriz', 'Camila', 2], array_slice($row, 15));
        $this->assertSame(['', '', 0], array_slice($filtered['Establecimientos'][2], 15));
        $this->assertSame(['Ana', 'Beatriz', 'Camila'], array_column(array_slice($filtered['Responsables y coberturas'], 1), 3));
        $this->assertSame(['Cobertura temporal', '06/11/2026', '06/11/2026', '', 'Vigente'],
            array_slice($filtered['Responsables y coberturas'][3], 4));
        $this->assertSame('Inactivo', $complete['Establecimientos'][3][14]);
        $this->assertSame(['Vigente', 'Vigente', 'Vigente', 'Vencida', 'Programada', 'Finalizada'],
            array_column(array_slice($complete['Responsables y coberturas'], 1), 8));
        $this->assertSame(['Departamento', 'Managua'], $filtered['Alcance'][4]);
        $this->assertCount(4, $complete['Alcance']);
        $auditDetails = AuditLog::where('action', 'establishment.master_exported')->orderBy('id')->get()
            ->pluck('details')->map(fn (array $details): array => collect($details)->sortKeys()->all());
        $this->assertSame([
            ['filters' => ['department' => 'Managua'], 'relationships' => 3, 'rows' => 2, 'scope' => 'filtered'],
            ['filters' => [], 'relationships' => 6, 'rows' => 3, 'scope' => 'complete'],
        ], $auditDetails->all());
    }

    #[DataProvider('downloadOrigins')]
    public function test_coordinator_can_download_from_both_directories(string $component, string $action): void
    {
        $this->withoutVite();
        $actor = User::factory()->coordinator()->create();
        Pharmacy::factory()->create(['department' => 'León']);

        Livewire::actingAs($actor)->test($component)->set('department', 'León')->call($action)
            ->assertFileDownloaded(contentType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public static function downloadOrigins(): array
    {
        return [
            'assignments' => [AssignmentsIndex::class, 'exportFilteredMaster'],
            'establishments' => [EstablishmentsIndex::class, 'exportCompleteMaster'],
        ];
    }

    public function test_unfiltered_master_requires_the_explicit_complete_action(): void
    {
        $actor = User::factory()->coordinator()->create();

        try {
            app(EstablishmentExchange::class)->exportMaster($actor, []);
            $this->fail('La descarga sin filtros se autorizó por el flujo filtrado.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseMissing('audit_logs', ['action' => 'establishment.master_exported']);
    }

    #[DataProvider('forbiddenRoles')]
    public function test_master_service_rejects_unauthorized_actors(UserRole $role, bool $active): void
    {
        $actor = User::factory()->create(['role' => $role, 'is_active' => $active]);

        try {
            app(EstablishmentExchange::class)->exportMaster($actor, [], true);
            $this->fail('La descarga se autorizó para un usuario sin permiso.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseMissing('audit_logs', ['action' => 'establishment.master_exported']);
    }

    public static function forbiddenRoles(): array
    {
        return [[UserRole::PROMOTER, true], [UserRole::ADMIN, true], [UserRole::COORDINATOR, false]];
    }

    private function relation(Pharmacy $pharmacy, string $name, array $attributes = []): void
    {
        $person = User::factory()->promoter()->create(['name' => $name]);
        PharmacyAssignment::factory()->create($attributes + ['pharmacy_id' => $pharmacy->id, 'promoter_id' => $person->id]);
    }

    private function read(string $path): array
    {
        $reader = new Reader;
        $sheets = [];
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $sheets[$sheet->getName()][] = $row->toArray();
                }
            }
        } finally {
            $reader->close();
            unlink($path);
        }

        return $sheets;
    }
}
