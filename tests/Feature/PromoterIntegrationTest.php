<?php

namespace Tests\Feature;

use App\Livewire\Promoter\Visits\History;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Services\SchedulePlanner;
use App\Services\VisitRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PromoterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Integration tests require isolated SQLite :memory:.');
        }
    }

    public function test_new_routes_render_the_approved_navigation_and_correct_context(): void
    {
        $this->actingAs(User::factory()->promoter()->create());
        foreach ([
            '/promoter' => 'Inicio',
            '/promoter/pharmacies' => 'Mis farmacias',
            '/promoter/requests' => 'Mis solicitudes',
            '/promoter/planning' => 'Planificación',
            '/promoter/visits' => 'Visitas',
            '/promoter/visits/register' => 'Visitas',
            '/promoter/visits/history' => 'Historial',
        ] as $path => $context) {
            $response = $this->get($path)->assertOk();
            $html = $response->getContent();
            $this->assertSame(1, preg_match('/class="promoter-topbar-section">\s*'.preg_quote($context, '/').'\s*<\/span>/u', $html));
            $this->assertSame(1, substr_count($html, 'aria-current="page"'));
            foreach (['promoter.dashboard', 'promoter.pharmacies.index', 'promoter.requests.index', 'promoter.planning.index', 'promoter.visits.index', 'promoter.visits.history'] as $route) {
                $response->assertSee(route($route), false);
            }
            $response->assertSee('promoter-skip-link', false)->assertSee('css/promoter.css', false);
        }
        $this->assertSame(0, DB::table('monthly_schedules')->count());
        $this->assertSame(0, DB::table('visits')->count());
        $this->assertSame(0, DB::table('visit_reasons')->count());
        $this->assertSame(0, DB::table('checklist_items')->count());
    }

    public function test_factory_presentation_shared_assignments_planning_and_visits_coexist(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));
        $actor = User::factory()->promoter()->create();
        $other = User::factory()->promoter()->create();
        $pharmacy = Pharmacy::factory()->create([
            'name' => 'Ensayo 7', 'department' => 'León', 'city' => 'León',
        ]);
        foreach ([$actor, $other] as $promoter) {
            PharmacyAssignment::factory()->create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $promoter->id]);
        }
        $attributes = $pharmacy->fresh()->getAttributes();
        $planner = new SchedulePlanner;
        $schedule = $planner->create($actor, 2026, 10);
        $activity = $planner->addActivity($actor, $schedule->id, [
            'activity_type' => 'VISITA', 'scheduled_date' => '2026-10-15', 'pharmacy_id' => $pharmacy->id,
        ]);
        $planner->submit($actor, $schedule->id);
        $visit = (new VisitRecorder)->register($actor, [
            'idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'planned_activity_id' => $activity->id,
        ]);
        $this->assertSame('Ensayo 7', $pharmacy->fresh()->display_name);
        $this->assertSame('', $pharmacy->fresh()->branch_summary);
        $this->assertSame($visit->id, $pharmacy->visits()->sole()->id);
        $this->assertSame($visit->id, $actor->visits()->sole()->id);
        $this->assertSame($attributes, $pharmacy->fresh()->getAttributes());
        $this->assertSame(2, $pharmacy->assignments()->count());
        $this->actingAs($actor)->get('/promoter/visits/'.$visit->id)->assertOk();
        $this->actingAs($other)->get('/promoter/visits/'.$visit->id)->assertNotFound();
        $branch = Pharmacy::factory()->make(['name' => 'Ensayo 7 · Sucursal Central', 'department' => 'León', 'city' => 'León']);
        $this->assertSame('Ensayo 7', $branch->display_name);
        $this->assertSame('Sucursal Central', $branch->branch_summary);
    }

    public function test_visits_static_routes_do_not_collide_with_detail_or_pharmacy_routes(): void
    {
        $routes = app('router')->getRoutes();
        foreach ([
            '/promoter/visits/register' => 'promoter.visits.create',
            '/promoter/visits/history' => 'promoter.visits.history',
            '/promoter/visits/123' => 'promoter.visits.show',
            '/promoter/pharmacies/123' => 'promoter.pharmacies.show',
        ] as $path => $name) {
            $route = $routes->match(Request::create($path));
            $this->assertSame($name, $route->getName());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('role:promoter', $route->gatherMiddleware());
        }
    }

    public function test_planning_and_visits_migrations_roundtrip_without_changing_existing_catalog(): void
    {
        $actor = User::factory()->promoter()->create();
        $pharmacy = Pharmacy::factory()->create(['name' => 'Ensayo 9']);
        PharmacyAssignment::factory()->create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $actor->id]);
        $tables = ['users', 'pharmacies', 'pharmacy_assignments', 'pharmacy_changes', 'pharmacy_requests'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $migrations = array_map(fn ($path) => require $path, [
            database_path('migrations/2026_10_27_000001_create_planning_tables.php'),
            database_path('migrations/2026_10_27_000002_create_audit_logs_table.php'),
            database_path('migrations/2026_10_27_000003_create_visits_tables.php'),
        ]);
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
        $this->assertFalse(Schema::hasTable('monthly_schedules'));
        $this->assertFalse(Schema::hasTable('visits'));
        $this->assertSame($before, $snapshot());
        foreach ($migrations as $migration) {
            $migration->up();
        }
        foreach (['monthly_schedules', 'planned_weeks', 'planned_activities', 'audit_logs', 'visit_reasons', 'checklist_items', 'visits', 'visit_checklist', 'activity_reschedules'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertTrue(Schema::hasColumn('planned_activities', 'reschedule_count'));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->assertSame($before, $snapshot());
    }

    public function test_workflow_views_preserve_commercial_numbers_branch_references_and_formal_copy(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 16:00:00', 'UTC'));
        $actor = User::factory()->promoter()->create();
        $pharmacy = Pharmacy::factory()->create([
            'name' => 'Ensayo 74 · Sucursal Central', 'department' => 'León', 'city' => 'León',
            'neighborhood' => 'Barrio de prueba', 'address' => 'Dirección de prueba conservada.',
        ]);
        PharmacyAssignment::factory()->create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $actor->id]);
        $before = $pharmacy->fresh()->getAttributes();
        $planner = new SchedulePlanner;
        $schedule = $planner->create($actor, 2026, 10);
        $activity = $planner->addActivity($actor, $schedule->id, [
            'activity_type' => 'VISITA', 'scheduled_date' => '2026-10-15', 'pharmacy_id' => $pharmacy->id,
        ]);
        $planner->submit($actor, $schedule->id);
        $this->actingAs($actor);
        foreach (['/promoter/planning?mes=2026-10', '/promoter/visits', '/promoter/visits/register?actividad='.$activity->id] as $path) {
            $this->get($path)->assertOk()->assertSee('Ensayo 74')
                ->assertSee('Barrio de prueba')->assertSee('Dirección de prueba conservada.');
        }
        $this->get('/promoter/visits/register')->assertOk()
            ->assertSee('Ensayo 74')
            ->assertSee('Solo puede elegir farmacias activas que tiene asignadas.');
        $visit = (new VisitRecorder)->register($actor, [
            'idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'planned_activity_id' => $activity->id,
        ]);
        foreach (['/promoter/visits/history', '/promoter/visits/'.$visit->id] as $path) {
            $this->get($path)->assertOk()->assertSee('Ensayo 74')
                ->assertSee('Dirección de prueba conservada.');
        }
        $this->assertSame($before, $pharmacy->fresh()->getAttributes());
    }

    public function test_shared_history_pagination_keeps_visits_private(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 16:00:00', 'UTC'));
        $actor = User::factory()->promoter()->create();
        $other = User::factory()->promoter()->create();
        $pharmacy = Pharmacy::factory()->create(['name' => 'Ensayo paginación', 'department' => 'León', 'city' => 'León']);
        foreach ([$actor, $other] as $promoter) {
            PharmacyAssignment::factory()->create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $promoter->id]);
        }
        $recorder = new VisitRecorder;
        for ($i = 0; $i < 16; $i++) {
            $recorder->register($actor, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $pharmacy->id]);
        }
        $recorder->register($other, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $pharmacy->id]);
        Livewire::actingAs($actor)->test(History::class)
            ->assertSee('Página 1 de 2')->assertSee('Siguiente')->assertViewHas('visits', fn ($visits) => $visits->total() === 16)
            ->call('nextPage')->assertSee('Página 2 de 2')
            ->assertViewHas('visits', fn ($visits) => $visits->count() === 1 && $visits->first()->promoter_id === $actor->id);
    }
}
