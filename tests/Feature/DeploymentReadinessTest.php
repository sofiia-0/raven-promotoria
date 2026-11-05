<?php

namespace Tests\Feature;

use App\Support\DeploymentReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    public function test_preflight_reports_unsafe_configuration_without_exposing_values(): void
    {
        config(['app.debug' => true, 'services.resend.key' => 'synthetic-key-do-not-print', 'app.url' => 'http://localhost', 'visit_evidence.disk' => 'public']);

        $exit = Artisan::call('raven:deployment-preflight', ['--json' => true]);
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exit);
        $this->assertTrue($report['read_only']);
        $this->assertFalse($report['checks']['https_url']);
        $this->assertFalse($report['checks']['private_evidence']);
        $this->assertNull($report['database']);
        $this->assertStringNotContainsString('synthetic-key-do-not-print', $output);
    }

    public function test_database_preflight_inspects_schema_without_changing_catalogs(): void
    {
        $report = app(DeploymentReadiness::class)->report(true);

        $this->assertTrue($report['database']['connection_verified']);
        $this->assertSame([], $report['database']['pending_migrations']);
        $this->assertSame(0, $report['database']['request_conflicts']);
        $this->assertDatabaseCount('pharmacies', 0);
        $this->assertDatabaseCount('checklist_items', 0);
    }
}
