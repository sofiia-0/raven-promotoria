<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoverageMigrationCompatibilityTest extends TestCase
{
    public function test_additive_migration_and_rollback_preserve_legacy_assignment_columns(): void
    {
        // Independent memory connection even when the main suite runs in MySQL.
        config(['database.connections.coverage_migration_fixture' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        $connection = DB::connection('coverage_migration_fixture');
        $schema = $connection->getSchemaBuilder();
        $original = Schema::getFacadeRoot();
        Schema::swap($schema);
        try {
            $schema->create('users', fn (Blueprint $table) => $table->id());
            $schema->create('pharmacies', fn (Blueprint $table) => $table->id());
            $initial = require database_path('migrations/2026_10_25_000002_create_pharmacy_assignments_table.php');
            $initial->up();
            $connection->table('users')->insert(['id' => 1]);
            $connection->table('pharmacies')->insert(['id' => 1]);
            $connection->table('pharmacy_assignments')->insert(['id' => 1, 'pharmacy_id' => 1, 'promoter_id' => 1, 'assigned_at' => '2026-10-01 12:00:00', 'ended_at' => null, 'created_at' => '2026-10-01 12:00:00', 'updated_at' => '2026-10-01 12:00:00']);
            $before = (array) $connection->table('pharmacy_assignments')->first();
            $migration = require database_path('migrations/2026_11_03_000001_add_coverage_and_audit_to_pharmacy_assignments.php');
            $migration->up();
            $after = (array) $connection->table('pharmacy_assignments')->first();
            $this->assertSame($before, array_intersect_key($after, $before));
            $this->assertSame('responsible', $after['assignment_kind']);
            foreach (['coverage_starts_on', 'coverage_ends_on', 'created_by', 'ended_by', 'replacement_assignment_id'] as $field) {
                $this->assertNull($after[$field]);
            }
            $migration->down();
            $this->assertSame($before, (array) $connection->table('pharmacy_assignments')->first());
            $migration->up();
            $this->assertSame(1, $connection->table('pharmacy_assignments')->count());
        } finally {
            Schema::swap($original);
            DB::purge('coverage_migration_fixture');
        }
    }
}
