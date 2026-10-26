<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RavenCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function csv(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'raven_catalog_');
        $this->temporaryFiles[] = $file;
        file_put_contents($file, implode(',', [
            'source_reference', 'name', 'client_type', 'category', 'neighborhood', 'address',
            'city', 'department', 'frequency', 'dependents', 'shifts', 'phone', 'email', 'source_responsible',
        ])."\n".
            'REV-9001,"Farmacia Ejemplo · Sucursal 1",Cadena,AAA,Centro,Frente al parque,Managua,Managua,2,4,2,,,Promotora Ficticia'."\n".
            'REV-9002,"Farmacia Ejemplo · Sucursal 2",Cadena,AA,Centro,Plaza Dos,Managua,Managua,1,,,,,Promotora Ficticia'."\n");
        return $file;
    }

    public function test_dry_run_does_not_insert_records(): void
    {
        $csv = $this->csv();
        $this->assertSame(0, Artisan::call('raven:import-catalog', ['file' => $csv]));
        $this->assertSame(0, Pharmacy::count());
    }

    public function test_import_is_idempotent_and_does_not_overwrite_edits(): void
    {
        $csv = $this->csv();
        $args = ['file' => $csv, '--commit' => true];
        $this->assertSame(0, Artisan::call('raven:import-catalog', $args));
        $this->assertSame(2, Pharmacy::count());
        $this->assertSame(2, Pharmacy::whereNotNull('source_reference')->distinct()->count());
        $this->assertSame('Centro · Managua | Frente al parque', Pharmacy::where('source_reference', 'REV-9001')->first()->location_summary);

        Pharmacy::where('source_reference', 'REV-9001')->update(['name' => 'Nombre corregido en Raven']);
        $this->assertSame(0, Artisan::call('raven:import-catalog', $args));
        $this->assertSame(2, Pharmacy::count());
        $this->assertSame('Nombre corregido en Raven', Pharmacy::where('source_reference', 'REV-9001')->first()->name);
    }

    public function test_explicit_mapping_creates_only_one_current_assignment_per_pharmacy(): void
    {
        $csv = $this->csv();
        $promoter = User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => true]);
        $mapping = tempnam(sys_get_temp_dir(), 'raven_map_');
        $this->temporaryFiles[] = $mapping;
        file_put_contents($mapping, json_encode(['Promotora Ficticia' => $promoter->email]));

        $args = ['file' => $csv, '--commit' => true, '--mapping' => $mapping];
        $this->assertSame(0, Artisan::call('raven:import-catalog', $args));
        $this->assertSame(2, $promoter->assignedPharmacies()->count());
        $this->assertSame(0, Artisan::call('raven:import-catalog', $args));
        $this->assertSame(2, $promoter->assignedPharmacies()->count());
    }
}
