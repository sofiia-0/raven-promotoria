<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Promoter\Dashboard;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReason;
use App\Services\PromoterActivityMetrics;
use App\Services\SchedulePlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PromoterActivityMetricsTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $actor;

    private PromoterActivityMetrics $metrics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 16:00:00', 'UTC'));
        $this->actor = User::factory()->promoter()->create();
        $this->metrics = new PromoterActivityMetrics;
    }

    private function pharmacy(string $category = 'AAA', string $department = 'Managua'): Pharmacy
    {
        $p = Pharmacy::factory()->create(compact('category', 'department'));
        PharmacyAssignment::factory()->create(['pharmacy_id' => $p->id, 'promoter_id' => $this->actor->id]);

        return $p;
    }

    private function historicalVisit(Pharmacy $pharmacy, array $extra = []): Visit
    {
        return Visit::create([...['promoter_id' => $this->actor->id, 'pharmacy_id' => $pharmacy->id, 'idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'visited_at' => '2026-11-10 16:00:00'], ...$extra]);
    }

    public function test_coverage_counts_distinct_establishments_compliance_counts_plan_activities_and_distribution_counts_visits(): void
    {
        $a = $this->pharmacy();
        $b = $this->pharmacy('AA', 'León');
        $c = $this->pharmacy('AA', 'León');
        $planner = new SchedulePlanner;
        $schedule = $planner->create($this->actor, 2026, 11);
        $first = null;
        foreach ([[$a, '2026-11-02'], [$a, '2026-11-03'], [$b, '2026-11-04']] as [$p,$date]) {
            $activity = $planner->addActivity($this->actor, $schedule->id, ['scheduled_date' => $date, 'activity_type' => 'VISITA', 'pharmacy_id' => $p->id]);
            $first ??= $activity;
        }$planner->submit($this->actor, $schedule->id);
        $this->historicalVisit($a, ['planned_activity_id' => $first->id, 'attempt' => 0]);
        $this->historicalVisit($a);
        $this->historicalVisit($c);
        $this->historicalVisit($b, ['status' => 'NO_REALIZADA', 'visit_reason_id' => VisitReason::factory()->create()->id]);
        $m = $this->metrics->forMonth($this->actor, 2026, 11);
        $this->assertSame(2, $m['scheduledEstablishments']);
        $this->assertSame(1, $m['coveredEstablishments']);
        $this->assertSame(50.0, $m['coverage']);
        $this->assertSame(3, $m['scheduledVisits']);
        $this->assertSame(1, $m['completedPlanned']);
        $this->assertSame(33.3, $m['compliance']);
        $this->assertSame(3, $m['totalRealized']);
        $this->assertSame(2, $m['outsidePlan']);
        $this->assertSame([2, 1], array_column($m['categories'], 'total'));
        $this->assertSame([66.7, 33.3], array_column($m['departments'], 'percentage'));
    }

    public function test_empty_and_draft_only_periods_have_no_denominator_or_division_errors(): void
    {
        $m = $this->metrics->forMonth($this->actor, 2026, 11);
        $this->assertSame(0.0, $m['coverage']);
        $this->assertSame(0.0, $m['compliance']);
        $this->assertSame([], $m['categories']);
        $planner = new SchedulePlanner;
        $s = $planner->create($this->actor, 2026, 11);
        $planner->addActivity($this->actor, $s->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-11-21', 'pharmacy_id' => $this->pharmacy()->id]);
        $this->assertSame(0, $this->metrics->forMonth($this->actor, 2026, 11)['scheduledVisits']);
        Livewire::actingAs($this->actor)->test(Dashboard::class)->assertSee('No hay visitas realizadas para este período.');
    }

    public function test_nicaragua_month_boundaries_use_utc_timestamps_without_leaking_other_months(): void
    {
        $p = $this->pharmacy();
        foreach (['2026-11-01 05:59:59', '2026-11-01 06:00:00', '2026-12-01 05:59:59', '2026-12-01 06:00:00'] as $date) {
            $this->historicalVisit($p, ['visited_at' => $date]);
        }
        $this->assertSame(2, $this->metrics->forMonth($this->actor, 2026, 11)['totalRealized']);
        $this->assertSame(1, $this->metrics->forMonth($this->actor, 2026, 10)['totalRealized']);
        $this->assertSame(1, $this->metrics->forMonth($this->actor, 2026, 12)['totalRealized']);
    }

    public function test_assignment_changes_and_shared_establishments_preserve_own_activity_without_counting_another_promoter(): void
    {
        $p = $this->pharmacy();
        $this->historicalVisit($p);
        $other = User::factory()->promoter()->create();
        PharmacyAssignment::factory()->create(['pharmacy_id' => $p->id, 'promoter_id' => $other->id]);
        $this->historicalVisit($p, ['promoter_id' => $other->id]);
        $p->assignments()->where('promoter_id', $this->actor->id)->update(['ended_at' => now()]);
        $p->update(['is_active' => false]);
        $this->assertSame(1, $this->metrics->forMonth($this->actor, 2026, 11)['totalRealized']);
        Livewire::actingAs($this->actor)->test(Dashboard::class)->assertViewHas('totalActive', 0)->assertViewHas('metrics', fn ($m) => $m['totalRealized'] === 1);
    }

    public function test_distributions_keep_missing_labels_and_group_only_the_chart_tail_not_the_accessible_totals(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->historicalVisit($this->pharmacy('Categoría '.$i, 'Departamento '.$i));
        }$this->historicalVisit($this->pharmacy('', ''));
        $m = $this->metrics->forMonth($this->actor, 2026, 11);
        $this->assertCount(9, $m['categories']);
        $this->assertCount(6, $m['categorySegments']);
        $this->assertSame(9, array_sum(array_column($m['categorySegments'], 'total')));
        $this->assertContains('Sin categoría', array_column($m['categories'], 'label'));
        $this->assertContains('Sin departamento', array_column($m['departments'], 'label'));
        Livewire::actingAs($this->actor)->test(Dashboard::class)->assertSee('Ver todas las categorías')->assertSee('Otras categorías');
    }

    public function test_month_and_year_filters_are_scoped_to_the_authenticated_promoter_and_old_visits_remain_counted(): void
    {
        $p = $this->pharmacy();
        $this->historicalVisit($p, ['visited_at' => '2025-10-15 16:00:00']);
        $this->historicalVisit($p);
        Livewire::actingAs($this->actor)->test(Dashboard::class)->set('reportYear', '2025')->set('reportMonth', '10')->assertViewHas('metrics', fn ($m) => $m['totalRealized'] === 1 && $m['label'] === 'Octubre de 2025')->assertSee('2025');
        $other = User::factory()->promoter()->create();
        $this->historicalVisit($p, ['promoter_id' => $other->id, 'visited_at' => '2020-01-15 16:00:00']);
        $this->assertNotContains(2020, $this->metrics->years($this->actor));
    }

    public function test_roles_and_inactive_accounts_cannot_request_activity_metrics(): void
    {
        foreach ([UserRole::ADMIN, UserRole::COORDINATOR] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get('/promoter')->assertForbidden();
        }
        $this->actor->update(['is_active' => false]);
        try {
            $this->metrics->forMonth($this->actor, 2026, 11);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_last_scheduled_day_is_included_and_first_day_of_next_month_is_excluded(): void
    {
        $p = $this->pharmacy();
        $planner = new SchedulePlanner;
        $november = $planner->create($this->actor, 2026, 11);
        $last = $planner->addActivity($this->actor, $november->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-11-30', 'pharmacy_id' => $p->id]);
        $planner->submit($this->actor, $november->id);
        $december = $planner->create($this->actor, 2026, 12);
        $planner->addActivity($this->actor, $december->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-12-01', 'pharmacy_id' => $p->id]);
        $planner->submit($this->actor, $december->id);
        $this->travelTo(CarbonImmutable::parse('2026-11-30 18:00:00', 'UTC'));
        $this->historicalVisit($p, ['visited_at' => '2026-11-30 18:00:00', 'planned_activity_id' => $last->id, 'attempt' => 0]);
        $m = $this->metrics->forMonth($this->actor, 2026, 11);
        $this->assertSame(1, $m['scheduledVisits']);
        $this->assertSame(1, $m['scheduledEstablishments']);
        $this->assertSame(100.0, $m['coverage']);
        $this->assertSame(100.0, $m['compliance']);
    }

    public function test_invalid_period_is_rejected_without_queries_for_another_promoter(): void
    {
        $this->expectException(ValidationException::class);
        $this->metrics->forMonth($this->actor, 2026, 13);
    }
}
