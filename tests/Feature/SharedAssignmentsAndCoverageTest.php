<?php

namespace Tests\Feature;

use App\Livewire\Coordinator\Assignments\Show;
use App\Livewire\Coordinator\Supervision\Visits;
use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use App\Services\PharmacyAssignmentService;
use App\Services\PharmacyEditor;
use App\Services\PlanningProductivity;
use App\Services\SchedulePlanner;
use App\Services\VisitRecorder;
use App\Support\EstablishmentSearch;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class SharedAssignmentsAndCoverageTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $coordinator;

    private User $one;

    private User $two;

    private User $three;

    private Pharmacy $pharmacy;

    private PharmacyAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00:00', 'UTC'));
        $this->coordinator = User::factory()->coordinator()->create();
        [$this->one, $this->two, $this->three] = User::factory()->promoter()->count(3)->create()->all();
        $this->pharmacy = Pharmacy::factory()->create(['name' => 'Acuña', 'department' => 'Managua', 'city' => 'Managua']);
        $this->service = new PharmacyAssignmentService;
        $this->service->assignMany($this->coordinator, [$this->pharmacy->id], $this->one);
    }

    private function relation(User $promoter): PharmacyAssignment
    {
        return $this->pharmacy->assignments()->where('promoter_id', $promoter->id)->whereNull('ended_at')->sole();
    }

    public function test_multiple_responsibles_are_independent_and_add_is_idempotent(): void
    {
        foreach ([$this->two, $this->three, $this->two] as $promoter) {
            $this->service->assignMany($this->coordinator, [$this->pharmacy->id], $promoter);
        }
        $this->assertCount(3, $this->pharmacy->fresh()->activeAssignments);
        $this->assertNull($this->pharmacy->fresh()->currentAssignment);
        foreach ([$this->one, $this->two, $this->three] as $promoter) {
            $this->assertSame(1, EstablishmentSearch::forPromoter($promoter, 'Acu, Managua')->count());
            $this->assertSame($this->coordinator->id, $this->relation($promoter)->created_by);
        }
        $this->assertSame(3, AuditLog::where('action', 'assignment.added')->count());
        $this->expectException(DomainException::class);
        $this->service->reassign($this->pharmacy, $this->three);
    }

    public function test_retirement_selects_one_relation_and_retries_do_not_repeat_audit(): void
    {
        $this->service->assignMany($this->coordinator, [$this->pharmacy->id], $this->two);
        $id = $this->relation($this->one)->id;
        $this->assertTrue($this->service->end($this->coordinator, $this->pharmacy->id, $id));
        $this->assertFalse($this->service->end($this->coordinator, $this->pharmacy->id, $id));
        $this->assertFalse(EstablishmentSearch::forPromoter($this->one)->exists());
        $this->assertTrue(EstablishmentSearch::forPromoter($this->two)->exists());
        $this->assertSame(1, AuditLog::where('action', 'assignment.ended')->count());
        $this->assertSame($this->coordinator->id, PharmacyAssignment::findOrFail($id)->ended_by);
    }

    public function test_replacement_is_selective_idempotent_and_does_not_reassign_historical_visits(): void
    {
        $this->service->assignMany($this->coordinator, [$this->pharmacy->id], $this->two);
        $old = (new VisitRecorder)->register($this->one, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $this->pharmacy->id]);
        $before = $old->fresh()->getAttributes();
        $source = $this->relation($this->one);
        $new = $this->service->replace($this->coordinator, $this->pharmacy->id, $source->id, $this->three);
        $repeat = $this->service->replace($this->coordinator, $this->pharmacy->id, $source->id, $this->three);
        $this->assertSame($new->id, $repeat->id);
        $this->assertSame(2, $this->pharmacy->activeAssignments()->count());
        $this->assertTrue(EstablishmentSearch::forPromoter($this->two)->exists());
        $this->assertFalse(EstablishmentSearch::forPromoter($this->one)->exists());
        $this->assertSame($before, $old->fresh()->getAttributes());
        $this->actingAs($this->one)->get('/promoter/visits/'.$old->id)->assertOk();
        $this->actingAs($this->three)->get('/promoter/visits/'.$old->id)->assertNotFound();
        $this->actingAs($this->coordinator)->get('/coordinator/visits/'.$old->id)->assertOk()->assertSee($this->one->name);
        Livewire::actingAs($this->coordinator)->test(Visits::class, ['pharmacyId' => (string) $this->pharmacy->id])->assertSee($this->one->name);
        $this->assertSame(1, AuditLog::where('action', 'assignment.replaced')->count());
    }

    public function test_replacing_with_an_existing_responsible_does_not_duplicate_them(): void
    {
        $this->service->assignMany($this->coordinator, [$this->pharmacy->id], $this->two);
        $this->service->replace($this->coordinator, $this->pharmacy->id, $this->relation($this->one)->id, $this->two);
        $this->assertSame(1, $this->pharmacy->activeAssignments()->count());
        $this->assertSame(2, $this->pharmacy->assignments()->count());
    }

    public function test_coverage_includes_both_managua_days_and_expires_without_writes(): void
    {
        $coverage = $this->service->cover($this->coordinator, $this->pharmacy->id, $this->two, '2026-11-04', '2026-11-05');
        $again = $this->service->cover($this->coordinator, $this->pharmacy->id, $this->two, '2026-11-04', '2026-11-05');
        $this->assertSame($coverage->id, $again->id);
        $this->assertFalse(EstablishmentSearch::forPromoter($this->two)->exists());
        $this->travelTo(CarbonImmutable::parse('2026-11-04 06:00:00', 'UTC'));
        $this->assertTrue(EstablishmentSearch::forPromoter($this->two)->exists());
        $this->assertSame(1, $this->two->assignedPharmacies()->count());
        $this->travelTo(CarbonImmutable::parse('2026-11-06 05:59:59', 'UTC'));
        $this->assertTrue(EstablishmentSearch::forPromoter($this->two)->exists());
        $before = $coverage->fresh()->getAttributes();
        $this->travelTo(CarbonImmutable::parse('2026-11-06 06:00:00', 'UTC'));
        $this->assertFalse(EstablishmentSearch::forPromoter($this->two)->exists());
        $this->assertSame(0, $this->two->assignedPharmacies()->count());
        $this->assertSame($before, $coverage->fresh()->getAttributes());
        $this->assertSame('Vencida', $coverage->fresh()->period_status);
        $this->assertTrue(EstablishmentSearch::forPromoter($this->one)->exists());
    }

    public function test_coverage_permissions_apply_to_edit_planning_and_registration_then_revoke(): void
    {
        $coverage = $this->service->cover($this->coordinator, $this->pharmacy->id, $this->two, '2026-11-03', '2026-11-03');
        (new PharmacyEditor)->update($this->pharmacy->id, $this->two, ['phone' => '2222-3333']);
        $planner = new SchedulePlanner;
        $schedule = $planner->create($this->two, 2026, 11);
        $planner->addActivity($this->two, $schedule->id, ['activity_type' => 'VISITA', 'scheduled_date' => '2026-11-03', 'pharmacy_id' => $this->pharmacy->id]);
        $visit = (new VisitRecorder)->register($this->two, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $this->pharmacy->id]);
        $this->service->end($this->coordinator, $this->pharmacy->id, $coverage->id);
        $this->actingAs($this->two)->get('/promoter/pharmacies/'.$this->pharmacy->id)->assertNotFound();
        $this->actingAs($this->two)->get('/promoter/visits/'.$visit->id)->assertOk();
        $this->expectException(ValidationException::class);
        (new VisitRecorder)->register($this->two, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $this->pharmacy->id]);
    }

    public function test_overlap_is_rejected_without_ending_other_responsibles(): void
    {
        $this->service->cover($this->coordinator, $this->pharmacy->id, $this->two, '2026-11-03', '2026-11-06');
        try {
            $this->service->cover($this->coordinator, $this->pharmacy->id, $this->two, '2026-11-06', '2026-11-10');
            $this->fail('Overlapping coverage must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('coverageStartsOn', $e->errors());
        }
        $this->assertSame(2, $this->pharmacy->assignments()->count());
        $this->assertTrue(EstablishmentSearch::forPromoter($this->one)->exists());
    }

    public function test_bulk_add_rolls_back_all_changes_when_a_relation_conflicts(): void
    {
        $other = Pharmacy::factory()->create();
        $this->service->cover($this->coordinator, $other->id, $this->two, '2026-11-03', '2026-11-06');
        $audit = AuditLog::count();
        try {
            $this->service->assignMany($this->coordinator, [$this->pharmacy->id, $other->id], $this->two);
            $this->fail('Entire batch must roll back.');
        } catch (ValidationException) {
            $this->assertFalse($this->pharmacy->assignments()->where('promoter_id', $this->two->id)->exists());
            $this->assertSame($audit, AuditLog::count());
        }
    }

    public function test_wrong_pharmacy_id_cannot_end_another_relationship(): void
    {
        $other = Pharmacy::factory()->create();
        try {
            $this->service->end($this->coordinator, $other->id, $this->relation($this->one)->id);
            $this->fail('An assignment from another pharmacy must be rejected.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(EstablishmentSearch::forPromoter($this->one)->exists());
        }
    }

    public function test_promoter_cannot_create_or_end_coverage(): void
    {
        $this->actingAs($this->one);
        Livewire::test(Show::class, ['pharmacyId' => $this->pharmacy->id])->assertForbidden();
        $this->expectException(AccessDeniedHttpException::class);
        $this->service->cover($this->one, $this->pharmacy->id, $this->two, '2026-11-03', '2026-11-04');
    }

    public function test_coverage_form_and_early_finish_preserve_usual_responsible(): void
    {
        Livewire::actingAs($this->coordinator)->test(Show::class, ['pharmacyId' => $this->pharmacy->id])
            ->set('operation', 'coverage')->set('targetPromoterId', (string) $this->two->id)
            ->set('coverageStartsOn', '2026-11-03')->set('coverageEndsOn', '2026-11-05')
            ->call('assign')->assertHasNoErrors()->assertSee('Cobertura guardada');
        $coverage = $this->relation($this->two);
        Livewire::actingAs($this->coordinator)->test(Show::class, ['pharmacyId' => $this->pharmacy->id])
            ->call('endAssignment', $coverage->id)->assertHasNoErrors();
        $this->assertSame($this->coordinator->id, $coverage->fresh()->ended_by);
        $this->assertFalse(EstablishmentSearch::forPromoter($this->two)->exists());
        $this->assertTrue(EstablishmentSearch::forPromoter($this->one)->exists());
        $this->assertSame(1, AuditLog::where('action', 'coverage.ended')->count());
    }

    public function test_coverage_cannot_plan_past_its_end_and_preview_is_invalidated_on_early_finish(): void
    {
        $coverage = $this->service->cover($this->coordinator, $this->pharmacy->id, $this->two, '2026-11-03', '2026-11-05');
        $planner = new SchedulePlanner;
        $schedule = $planner->create($this->two, 2026, 11);
        $productivity = new PlanningProductivity;
        $bad = $productivity->previewVisits($this->two, $schedule->id, '2026-11-06', [$this->pharmacy->id]);
        $this->assertSame(1, $bad['conflicts']);
        $good = $productivity->previewVisits($this->two, $schedule->id, '2026-11-04', [$this->pharmacy->id]);
        $this->assertSame(1, $good['ready']);
        $this->service->end($this->coordinator, $this->pharmacy->id, $coverage->id);
        try {
            $productivity->addVisits($this->two, $schedule->id, '2026-11-04', [$this->pharmacy->id], null, $good['hash']);
            $this->fail('Stale coverage must not authorize writes.');
        } catch (ValidationException) {
            $this->assertSame(0, $schedule->activities()->count());
        }
    }

    public function test_coverage_dates_and_usual_relationship_conflicts_are_validated(): void
    {
        foreach ([['2026-11-05', '2026-11-03'], ['2026-11-31', '2026-12-01']] as [$start,$end]) {
            try {
                $this->service->cover($this->coordinator, $this->pharmacy->id, $this->two, $start, $end);
                $this->fail('Invalid period must fail.');
            } catch (ValidationException) {
                $this->assertSame(1, $this->pharmacy->assignments()->count());
            }
        }
        $this->expectException(ValidationException::class);
        $this->service->cover($this->coordinator, $this->pharmacy->id, $this->one, '2026-11-03', '2026-11-04');
    }

    public function test_stale_retired_assignment_cannot_be_replaced_and_bulk_retirement_is_selective(): void
    {
        $this->service->assignMany($this->coordinator, [$this->pharmacy->id], $this->two);
        $old = $this->relation($this->one);
        $result = $this->service->unassignMany($this->coordinator, [$this->pharmacy->id], $this->one->id);
        $this->assertSame(1, $result['changed']);
        try {
            $this->service->replace($this->coordinator, $this->pharmacy->id, $old->id, $this->three);
            $this->fail('A stale relation requires review.');
        } catch (ValidationException) {
            $this->assertSame($this->two->id,$this->pharmacy->fresh()->currentAssignment->promoter_id);
        }
        $this->assertSame(2,$this->pharmacy->assignments()->count());
    }
}
