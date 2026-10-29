<?php

namespace Tests\Feature;

use App\Livewire\Promoter\Planning\Index;
use App\Models\AuditLog;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Services\PlanningProductivity;
use App\Services\SchedulePlanner;
use App\Support\PlanningCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PlanningProductivityTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $actor;

    private MonthlySchedule $schedule;

    private SchedulePlanner $planner;

    private PlanningProductivity $productivity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 16:00:00', 'UTC'));
        $this->actor = User::factory()->promoter()->create();
        $this->planner = new SchedulePlanner;
        $this->productivity = new PlanningProductivity;
        $this->schedule = $this->planner->create($this->actor, 2026, 10);
    }

    private function pharmacy(?User $actor = null): Pharmacy
    {
        $p = Pharmacy::factory()->create(['department' => 'León', 'city' => 'León']);
        PharmacyAssignment::factory()->create(['pharmacy_id' => $p->id, 'promoter_id' => ($actor ?? $this->actor)->id]);

        return $p;
    }

    private function add(string $date, ?Pharmacy $p = null, string $type = 'VISITA', ?string $notes = null): PlannedActivity
    {
        return $this->planner->addActivity($this->actor, $this->schedule->id, ['activity_type' => $type, 'scheduled_date' => $date, 'pharmacy_id' => $p?->id, 'notes' => $notes]);
    }

    private function reject(callable $call, ?string $field = null): void
    {
        try {
            $call();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $e) {
            if ($field) {
                $this->assertArrayHasKey($field, $e->errors());
            } else {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_preview_of_fifteen_visits_is_read_only_then_confirmation_applies_all_and_audits_once(): void
    {
        $ids = [];
        for ($i = 0; $i < 15; $i++) {
            $ids[] = $this->pharmacy()->id;
        }
        $preview = $this->productivity->previewVisits($this->actor, $this->schedule->id, '2026-10-20', $ids, 'Seguimiento de prueba.');
        $this->assertSame(15, $preview['ready']);
        $this->assertSame(0, PlannedActivity::count());
        $this->assertSame(0, AuditLog::count());
        $created = $this->productivity->addVisits($this->actor, $this->schedule->id, '2026-10-20', $ids, 'Seguimiento de prueba.', $preview['hash']);
        $this->assertCount(15, $created);
        $this->assertSame(15, PlannedActivity::count());
        $this->assertSame(['Seguimiento de prueba.'], PlannedActivity::pluck('notes')->unique()->all());
        $this->assertSame($created, AuditLog::sole()->details['activity_ids']);
        $this->reject(fn () => $this->productivity->addVisits($this->actor, $this->schedule->id, '2026-10-20', $ids, 'Seguimiento de prueba.', $preview['hash']), 'batch');
        $this->assertSame(15, PlannedActivity::count());
        $this->assertSame(1, AuditLog::count());
    }

    public function test_batch_detects_existing_visits_and_does_not_insert_the_other_rows(): void
    {
        $a = $this->pharmacy();
        $b = $this->pharmacy();
        $original = $this->add('2026-10-20', $a);
        $preview = $this->productivity->previewVisits($this->actor, $this->schedule->id, '2026-10-20', [$a->id, $b->id]);
        $this->assertSame(1, $preview['conflicts']);
        $this->reject(fn () => $this->productivity->addVisits($this->actor, $this->schedule->id, '2026-10-20', [$a->id, $b->id], null, $preview['hash']), 'batch');
        $this->assertSame([$original->id], PlannedActivity::pluck('id')->all());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_repeated_ids_invalid_dates_and_out_of_month_dates_are_rejected(): void
    {
        $p = $this->pharmacy();
        $this->reject(fn () => $this->productivity->previewVisits($this->actor, $this->schedule->id, '2026-10-20', [$p->id, $p->id]));
        foreach (['2026-10-32', '2026-11-01', '2026-09-30', 'not-a-date'] as $date) {
            $this->reject(fn () => $this->productivity->previewVisits($this->actor, $this->schedule->id, $date, [$p->id]), 'scheduledDate');
        }
        $this->assertSame(0, PlannedActivity::count());
    }

    public function test_foreign_inactive_and_ended_assignments_never_enter_a_batch(): void
    {
        $good = $this->pharmacy();
        $foreign = $this->pharmacy(User::factory()->promoter()->create());
        $inactive = $this->pharmacy();
        $inactive->update(['is_active' => false]);
        $ended = $this->pharmacy();
        $ended->assignments()->update(['ended_at' => now()]);
        foreach ([$foreign, $inactive, $ended] as $bad) {
            $p = $this->productivity->previewVisits($this->actor, $this->schedule->id, '2026-10-20', [$good->id, $bad->id]);
            $this->assertSame('Establecimiento no disponible', $p['rows'][1]['name']);
            $this->reject(fn () => $this->productivity->addVisits($this->actor, $this->schedule->id, '2026-10-20', [$good->id, $bad->id], null, $p['hash']), 'batch');
        }
        $this->assertSame(0, PlannedActivity::count());
    }

    public function test_shared_assignments_are_supported_and_lost_permission_after_preview_blocks_every_insert(): void
    {
        $p = $this->pharmacy();
        $other = User::factory()->promoter()->create();
        PharmacyAssignment::factory()->create(['pharmacy_id' => $p->id, 'promoter_id' => $other->id]);
        $preview = $this->productivity->previewVisits($this->actor, $this->schedule->id, '2026-10-20', [$p->id]);
        $this->assertSame(1, $preview['ready']);
        $p->assignments()->where('promoter_id', $this->actor->id)->update(['ended_at' => now()]);
        $this->reject(fn () => $this->productivity->addVisits($this->actor, $this->schedule->id, '2026-10-20', [$p->id], null, $preview['hash']), 'batch');
        $this->assertSame(0, PlannedActivity::count());
        $this->assertSame(2, $p->assignments()->count());
    }

    public function test_failure_during_insertion_rolls_back_the_entire_batch_and_audit(): void
    {
        $ids = [$this->pharmacy()->id, $this->pharmacy()->id];
        $preview = $this->productivity->previewVisits($this->actor, $this->schedule->id, '2026-10-20', $ids);
        $calls = 0;
        $real = $this->planner;
        $fake = \Mockery::mock(SchedulePlanner::class);
        $fake->shouldReceive('addActivity')->twice()->andReturnUsing(function (...$args) use (&$calls, $real) {
            if (++$calls === 2) {
                throw new \RuntimeException('Synthetic failure.');
            }

            return $real->addActivity(...$args);
        });
        try {
            (new PlanningProductivity($fake))->addVisits($this->actor, $this->schedule->id, '2026-10-20', $ids, null, $preview['hash']);
            $this->fail('Expected synthetic failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic failure.', $e->getMessage());
        }
        $this->assertSame(0, PlannedActivity::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_own_schedule_and_active_promoter_are_required_for_both_operations(): void
    {
        $intruder = User::factory()->promoter()->create();
        try {
            $this->productivity->previewVisits($intruder, $this->schedule->id, '2026-10-20', [1]);
            $this->fail();
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        try {
            $this->productivity->previewWeek($intruder, $this->schedule->id, 1, 2);
            $this->fail();
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        $intruder->is_active = false;
        try {
            $this->productivity->previewVisits($intruder, $this->schedule->id, '2026-10-20', [1]);
            $this->fail();
        } catch (AccessDeniedHttpException) {
            $this->assertTrue(true);
        }
    }

    public function test_week_copy_preserves_weekdays_activity_types_notes_source_and_existing_target_rows(): void
    {
        $p = $this->pharmacy();
        $a = $this->add('2026-10-05', $p, 'VISITA', 'Nota de lunes.');
        $b = $this->add('2026-10-07', null, 'REUNION', 'Reunión de prueba.');
        $existing = $this->add('2026-10-16', null, 'OTRA', 'Otra actividad.');
        $before = $a->fresh()->getAttributes();
        $weeks = $this->schedule->weeks;
        $src = $weeks[1];
        $dst = $weeks[2];
        $preview = $this->productivity->previewWeek($this->actor, $this->schedule->id, $src->id, $dst->id);
        $this->assertSame(['2026-10-12', '2026-10-14'], array_column($preview['rows'], 'date'));
        $this->assertSame(3, PlannedActivity::count());
        $created = $this->productivity->copyWeek($this->actor, $this->schedule->id, $src->id, $dst->id, $preview['hash']);
        $this->assertCount(2, $created);
        $this->assertSame(5, PlannedActivity::count());
        $this->assertSame($before, $a->fresh()->getAttributes());
        $this->assertTrue($existing->fresh()->exists);
        $new = PlannedActivity::whereIn('id', $created)->orderBy('scheduled_date')->get();
        $this->assertSame(['VISITA', 'REUNION'], $new->map(fn ($a) => $a->activity_type->value)->all());
        $this->assertSame(['Nota de lunes.', 'Reunión de prueba.'], $new->pluck('notes')->all());
        $this->assertSame('schedule.week_copied', AuditLog::sole()->action);
    }

    public function test_copy_duplicates_block_the_whole_copy_including_non_visit_duplicates(): void
    {
        $p = $this->pharmacy();
        $this->add('2026-10-05', $p);
        $this->add('2026-10-06', null, 'REUNION', 'Misma reunión.');
        $this->add('2026-10-12', $p);
        $this->add('2026-10-13', null, 'REUNION', 'Misma reunión.');
        $weeks = $this->schedule->weeks;
        $preview = $this->productivity->previewWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id);
        $this->assertSame(2, $preview['conflicts']);
        $this->reject(fn () => $this->productivity->copyWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id, $preview['hash']), 'copy');
        $this->assertSame(4, PlannedActivity::count());
    }

    public function test_incomplete_last_week_lists_missing_days_and_needs_explicit_omission_confirmation(): void
    {
        $this->add('2026-10-19', $this->pharmacy());
        $this->add('2026-10-25', $this->pharmacy());
        $weeks = $this->schedule->weeks;
        $p = $this->productivity->previewWeek($this->actor, $this->schedule->id, $weeks[3]->id, $weeks[4]->id);
        $this->assertSame(1, $p['ready']);
        $this->assertSame(1, $p['omitted']);
        $this->assertNull($p['rows'][1]['date']);
        $this->reject(fn () => $this->productivity->copyWeek($this->actor, $this->schedule->id, $weeks[3]->id, $weeks[4]->id, $p['hash']), 'copy');
        $this->assertSame(2, PlannedActivity::count());
        $ids = $this->productivity->copyWeek($this->actor, $this->schedule->id, $weeks[3]->id, $weeks[4]->id, $p['hash'], true);
        $this->assertCount(1, $ids);
        $this->assertSame('2026-10-26', PlannedActivity::findOrFail($ids[0])->scheduled_date->toDateString());
        $this->assertCount(1, AuditLog::sole()->details['omitted']);
    }

    public function test_incomplete_first_week_and_merged_raven_week_never_guess_target_dates(): void
    {
        $this->add('2026-10-05', $this->pharmacy());
        $weeks = $this->schedule->weeks;
        $p = $this->productivity->previewWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[0]->id);
        $this->assertSame(1, $p['omitted']);
        $this->assertSame(0, $p['ready']);
        $this->travelTo(CarbonImmutable::parse('2026-08-15 16:00:00', 'UTC'));
        $this->schedule = $this->planner->create($this->actor, 2026, 8);
        $this->add('2026-08-01', $this->pharmacy());
        $this->add('2026-08-03', $this->pharmacy());
        $weeks = $this->schedule->weeks;
        $p = $this->productivity->previewWeek($this->actor, $this->schedule->id, $weeks[0]->id, $weeks[1]->id);
        $this->assertSame(1, $p['omitted']);
        $this->assertSame(1, $p['ready']);
        $this->assertSame('2026-08-10', $p['rows'][1]['date']);
    }

    public function test_week_identity_empty_source_and_cross_month_target_are_validated(): void
    {
        $weeks = $this->schedule->weeks;
        $nov = $this->planner->create($this->actor, 2026, 11);
        foreach ([[$weeks[0]->id, $weeks[0]->id], [$weeks[0]->id, $nov->weeks->first()->id], [$weeks[0]->id, $weeks[1]->id]] as [$s,$d]) {
            $this->reject(fn () => $this->productivity->previewWeek($this->actor, $this->schedule->id, $s, $d), 'copy');
        }
    }

    public function test_stale_source_target_notes_and_assignment_block_confirmation_without_side_effects(): void
    {
        $p = $this->pharmacy();
        $source = $this->add('2026-10-05', $p);
        $weeks = $this->schedule->weeks;
        $preview = $this->productivity->previewWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id);
        $source->update(['notes' => 'Cambio posterior.']);
        $this->reject(fn () => $this->productivity->copyWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id, $preview['hash']), 'copy');
        $preview = $this->productivity->previewWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id);
        $this->add('2026-10-15', null, 'REUNION');
        $this->reject(fn () => $this->productivity->copyWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id, $preview['hash']), 'copy');
        $preview = $this->productivity->previewWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id);
        $p->update(['is_active' => false]);
        $this->reject(fn () => $this->productivity->copyWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id, $preview['hash']), 'copy');
        $this->assertSame(2, PlannedActivity::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_submitted_schedules_remain_byte_for_byte_unchanged_and_preview_cannot_unlock_them(): void
    {
        $p = $this->pharmacy();
        $this->add('2026-10-05', $p);
        $weeks = $this->schedule->weeks;
        $preview = $this->productivity->previewWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id);
        $this->planner->submit($this->actor, $this->schedule->id);
        $snapshot = PlannedActivity::get()->toJson();
        $attributes = $this->schedule->fresh()->getAttributes();
        $this->reject(fn () => $this->productivity->copyWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id, $preview['hash']), 'submit');
        $this->reject(fn () => $this->productivity->previewVisits($this->actor, $this->schedule->id, '2026-10-20', [$p->id]), 'submit');
        $this->assertSame($snapshot, PlannedActivity::get()->toJson());
        $this->assertSame($attributes, $this->schedule->fresh()->getAttributes());
    }

    public function test_livewire_selection_removal_review_and_confirmation_are_separate_and_month_changes_clear_previews(): void
    {
        $a = $this->pharmacy();
        $b = $this->pharmacy();
        $c = Livewire::actingAs($this->actor)->test(Index::class)->call('selectDay', '2026-10-20')->call('stagePharmacy', $a->id)->call('stagePharmacy', $b->id)->assertSet('selectedPharmacyIds', [$a->id, $b->id])->call('removeStagedPharmacy', $a->id)->assertSet('selectedPharmacyIds', [$b->id])->call('reviewBatch')->assertHasNoErrors();
        $this->assertSame(0, PlannedActivity::count());
        $c->call('confirmBatch')->assertHasNoErrors();
        $this->assertSame(1, PlannedActivity::count());
        $c->call('stagePharmacy', $a->id)->call('reviewBatch')->call('nextMonth')->assertSet('period', '2026-11')->assertSet('selectedPharmacyIds', [])->assertSet('batchPreview', null)->assertSet('copyPreview', null);
        $c->call('confirmBatch')->assertHasErrors('batch');
        $this->assertSame(1, PlannedActivity::count());
    }

    public function test_copy_failure_rolls_back_new_rows_but_preserves_originals_and_no_audit_is_written(): void
    {
        $this->add('2026-10-05', $this->pharmacy());
        $this->add('2026-10-06', $this->pharmacy());
        $weeks = $this->schedule->weeks;
        $before = PlannedActivity::orderBy('id')->get()->toJson();
        $preview = $this->productivity->previewWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id);
        $calls = 0;
        $real = $this->planner;
        $fake = \Mockery::mock(SchedulePlanner::class);
        $fake->shouldReceive('addActivity')->twice()->andReturnUsing(function (...$args) use (&$calls, $real) {
            if (++$calls === 2) {
                throw new \RuntimeException('Synthetic copy failure.');
            }

            return $real->addActivity(...$args);
        });
        try {
            (new PlanningProductivity($fake))->copyWeek($this->actor, $this->schedule->id, $weeks[1]->id, $weeks[2]->id, $preview['hash']);
            $this->fail();
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic copy failure.', $e->getMessage());
        }
        $this->assertSame($before, PlannedActivity::orderBy('id')->get()->toJson());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_livewire_copy_requires_review_then_confirms_and_can_cancel_without_changes(): void
    {
        $this->add('2026-10-05', $this->pharmacy());
        $weeks = $this->schedule->weeks;
        $c = Livewire::actingAs($this->actor)->test(Index::class)->set('copySourceWeek', (string) $weeks[1]->id)->set('copyTargetWeek', (string) $weeks[2]->id)->call('confirmCopy')->assertHasErrors('copy')->call('reviewCopy')->assertHasNoErrors();
        $this->assertSame(1, PlannedActivity::count());
        $c->call('cancelCopy')->assertSet('copyPreview', null);
        $this->assertSame(1, PlannedActivity::count());
        $c->call('reviewCopy')->call('confirmCopy')->assertHasNoErrors()->assertSet('copyPreview', null);
        $this->assertSame(2, PlannedActivity::count());
        $this->assertSame(1, AuditLog::count());
    }

    public function test_pending_selection_cannot_be_lost_by_submitting_the_schedule(): void
    {
        $p = $this->pharmacy();
        $this->add('2026-10-19', null, 'REUNION');
        $c = Livewire::actingAs($this->actor)->test(Index::class)->call('selectDay', '2026-10-20')->call('stagePharmacy', $p->id)->call('submit')->assertHasErrors('submit');
        $this->assertTrue($this->schedule->fresh()->isDraft());
        $c->call('clearSelection')->call('submit')->assertHasNoErrors();
        $this->assertFalse($this->schedule->fresh()->isDraft());
    }

    public function test_clearing_the_entry_date_does_not_silently_restore_a_previous_date(): void
    {
        $p = $this->pharmacy();
        Livewire::actingAs($this->actor)->test(Index::class)
            ->call('selectDay', '2026-10-20')->set('scheduledDate', '')
            ->assertSet('scheduledDate', '')->call('stagePharmacy', $p->id)
            ->assertHasErrors('scheduledDate')->assertSet('selectedPharmacyIds', []);
        $this->assertSame(0, PlannedActivity::count());
    }

    public function test_distinct_historical_records_are_not_consolidated_by_matching_names_or_addresses(): void
    {
        $a = $this->pharmacy();
        $b = $this->pharmacy();
        $a->update(['name' => 'Ensayo compartido · Atención A', 'neighborhood' => null, 'address' => 'Calle 7.']);
        $b->update(['name' => 'Ensayo compartido · Atención B', 'neighborhood' => null, 'address' => 'Calle 7.']);
        $p = $this->productivity->previewVisits($this->actor, $this->schedule->id, '2026-10-20', [$a->id, $b->id]);
        $this->assertSame(2, $p['ready']);
        $this->assertSame(['Atención A', 'Atención B'], array_column($p['rows'], 'selection_identifier'));
        $this->productivity->addVisits($this->actor, $this->schedule->id, '2026-10-20', [$a->id, $b->id], null, $p['hash']);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], PlannedActivity::pluck('pharmacy_id')->all());
        $this->assertSame('Ensayo compartido · Atención A', $a->fresh()->name);
        $this->assertSame('Ensayo compartido · Atención B', $b->fresh()->name);
    }

    public function test_calendar_day_week_and_date_navigation_rejects_other_months_and_keeps_weekday_alignment(): void
    {
        foreach ([[2026, 8], [2026, 11], [2028, 2]] as [$year,$month]) {
            $date = CarbonImmutable::create($year, $month, 1);
            $days = PlanningCalendar::days($date);
            $this->assertSame(0, count($days) % 7);
            $this->assertSame(1, CarbonImmutable::parse($days[0]['date'])->dayOfWeekIso);
            $this->assertSame($date->daysInMonth, count(array_filter($days, fn ($d) => $d['in_month'])));
        }
        $c = Livewire::actingAs($this->actor)->test(Index::class)->call('selectDay', '2026-10-20')->assertSet('scheduledDate', '2026-10-20')->call('setPlanningView', 'weeks')->assertSee('Semana 4');
        $c->call('selectDay', '2026-11-01')->assertHasErrors('selectedDate')->call('selectDay', '2026-10-32')->assertHasErrors('selectedDate');
        $c->call('selectWeek', $this->schedule->weeks[0]->id)->assertSet('selectedDate', '2026-10-01');
    }
}
