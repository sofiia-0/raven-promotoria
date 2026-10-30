<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Livewire\Promoter\Planning\Index;
use App\Models\AuditLog;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Services\PlanningProductivity;
use App\Services\SchedulePlanner;
use App\Services\VisitRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PlanningCrossMonthCopyTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $actor;

    private MonthlySchedule $origin;

    private MonthlySchedule $target;

    private Pharmacy $pharmacy;

    private PlanningProductivity $service;

    private SchedulePlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-15 16:00:00', 'UTC'));
        $this->actor = User::factory()->promoter()->create();
        $this->planner = new SchedulePlanner;
        $this->service = new PlanningProductivity;
        $this->origin = $this->planner->create($this->actor, 2026, 9);
        $this->target = $this->planner->create($this->actor, 2026, 10);
        $this->pharmacy = Pharmacy::factory()->create();
        PharmacyAssignment::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'promoter_id' => $this->actor->id]);
    }

    private function add(string $date = '2026-09-07', string $type = 'VISITA', string $notes = 'Seguimiento de prueba.'): PlannedActivity
    {
        return $this->planner->addActivity($this->actor, $this->origin->id, ['scheduled_date' => $date, 'activity_type' => $type, 'pharmacy_id' => $type === 'VISITA' ? $this->pharmacy->id : null, 'notes' => $notes]);
    }

    private function preview(): array
    {
        return $this->service->previewWeek($this->actor, $this->target->id, $this->origin->weeks[1]->id, $this->target->weeks[1]->id, $this->origin->id);
    }

    private function confirm(array $preview): array
    {
        return $this->service->copyWeek($this->actor, $this->target->id, $this->origin->weeks[1]->id, $this->target->weeks[1]->id, $preview['hash'], false, $this->origin->id);
    }

    private function rejected(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_submitted_previous_month_is_read_only_and_only_planning_fields_are_copied(): void
    {
        $activity = $this->add();
        $this->add('2026-09-09', 'REUNION', 'Reunión comercial.');
        $this->planner->submit($this->actor, $this->origin->id);
        (new VisitRecorder)->register($this->actor, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'planned_activity_id' => $activity->id, 'notes' => 'Resultado histórico, no copiar.', 'checklist' => []]);
        $this->travelTo(CarbonImmutable::parse('2026-10-15 16:00:00', 'UTC'));
        $before = $this->origin->fresh()->getAttributes();
        $source = $this->origin->activities()->get()->toJson();
        $history = DB::table('visits')->get()->toJson();
        $preview = $this->preview();
        $this->assertSame(2, $preview['ready']);
        $this->assertSame(['2026-10-05', '2026-10-07'], array_column($preview['rows'], 'date'));
        $this->assertSame(0, $this->target->activities()->count());
        $ids = $this->confirm($preview);
        $copied = PlannedActivity::whereIn('id', $ids)->orderBy('scheduled_date')->get();
        $this->assertSame(['VISITA', 'REUNION'], $copied->map(fn ($a) => $a->activity_type->value)->all());
        $this->assertSame(['Seguimiento de prueba.', 'Reunión comercial.'], $copied->pluck('notes')->all());
        $this->assertTrue($copied->every(fn ($a) => $a->reschedule_count === 0 && $a->visits()->count() === 0 && $a->reschedules()->count() === 0));
        $this->assertSame($before, $this->origin->fresh()->getAttributes());
        $this->assertSame($source, $this->origin->activities()->get()->toJson());
        $this->assertSame($history, DB::table('visits')->get()->toJson());
        $audit = AuditLog::where('action', 'schedule.week_copied')->sole();
        $this->assertSame($this->origin->id, $audit->details['source_schedule_id']);
        $this->assertSame($this->target->id, $audit->details['target_schedule_id']);
    }

    public function test_month_options_only_include_own_existing_current_or_previous_schedules(): void
    {
        $this->planner->create($this->actor, 2026, 11);
        $other = User::factory()->promoter()->create();
        $this->travelTo(CarbonImmutable::parse('2026-08-15 16:00:00', 'UTC'));
        $this->planner->create($other, 2026, 8);
        $c = Livewire::actingAs($this->actor)->test(Index::class, ['period' => '2026-10']);
        $c->assertViewHas('copyMonths', fn ($months) => $months->pluck('id')->all() === [$this->target->id, $this->origin->id])
            ->assertSee('Mes de origen')->assertDontSee('Agosto de 2026')->assertDontSee('Noviembre de 2026');
    }

    public function test_foreign_future_and_mismatched_source_or_target_cannot_be_read_or_copied(): void
    {
        $this->add();
        $other = User::factory()->promoter()->create();
        $foreign = $this->planner->create($other, 2026, 9);
        $future = $this->planner->create($this->actor, 2026, 11);
        foreach ([[$foreign->weeks[1]->id, null], [$future->weeks[1]->id, null], [$this->origin->weeks[1]->id, $foreign->id], [$this->origin->weeks[1]->id, $this->target->id]] as [$week, $month]) {
            $this->rejected(fn () => $this->service->previewWeek($this->actor, $this->target->id, $week, $this->target->weeks[1]->id, $month));
        }
        $this->rejected(fn () => $this->service->previewWeek($this->actor, $this->target->id, $this->origin->weeks[1]->id, $foreign->weeks[1]->id));
        $this->assertSame(0, $this->target->activities()->count());
    }

    public function test_partial_target_month_requires_explicit_exclusions_and_never_guesses_dates(): void
    {
        $this->add();
        $this->add('2026-09-13');
        $source = $this->origin->weeks[1]->id;
        $target = $this->target->weeks[4]->id;
        $p = $this->service->previewWeek($this->actor, $this->target->id, $source, $target);
        $this->assertSame(1, $p['ready']);
        $this->assertSame(1, $p['omitted']);
        $this->assertSame('2026-10-26', $p['rows'][0]['date']);
        $this->assertNull($p['rows'][1]['date']);
        $this->rejected(fn () => $this->service->copyWeek($this->actor, $this->target->id, $source, $target, $p['hash']));
        $this->assertSame(0, $this->target->activities()->count());
        $this->assertCount(1, $this->service->copyWeek($this->actor, $this->target->id, $source, $target, $p['hash'], true));
    }

    public function test_repeated_copy_and_existing_duplicates_block_the_entire_operation(): void
    {
        $this->add();
        $this->add('2026-09-09', 'REUNION');
        $preview = $this->preview();
        $this->confirm($preview);
        $this->rejected(fn () => $this->confirm($preview));
        $p = $this->preview();
        $this->assertSame(2, $p['conflicts']);
        $this->rejected(fn () => $this->confirm($p));
        $this->assertSame(2, $this->target->activities()->count());
        $this->assertSame(1, AuditLog::where('action', 'schedule.week_copied')->count());
    }

    public function test_current_assignments_and_active_status_are_rechecked_after_preview(): void
    {
        $this->add();
        $p = $this->preview();
        PharmacyAssignment::where('promoter_id', $this->actor->id)->update(['ended_at' => now()]);
        $this->rejected(fn () => $this->confirm($p));
        $this->assertSame(1, $this->preview()['conflicts']);
        PharmacyAssignment::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'promoter_id' => $this->actor->id]);
        $p = $this->preview();
        $this->pharmacy->update(['is_active' => false]);
        $this->rejected(fn () => $this->confirm($p));
        $this->assertSame(0, $this->target->activities()->count());
    }

    public function test_shared_assignment_is_valid_but_never_grants_access_to_another_promoters_schedule(): void
    {
        $this->add();
        $other = User::factory()->promoter()->create();
        PharmacyAssignment::factory()->create(['pharmacy_id' => $this->pharmacy->id, 'promoter_id' => $other->id]);
        $this->assertCount(1, $this->confirm($this->preview()));
        Livewire::actingAs($other)->test(Index::class, ['period' => '2026-10'])->assertDontSee('Septiembre de 2026');
    }

    public function test_source_and_target_changes_invalidate_review_without_partial_inserts(): void
    {
        $source = $this->add();
        $p = $this->preview();
        $source->update(['notes' => 'Cambio después de la revisión.']);
        $this->rejected(fn () => $this->confirm($p));
        $p = $this->preview();
        $this->planner->addActivity($this->actor, $this->target->id, ['scheduled_date' => '2026-10-08', 'activity_type' => 'OTRA', 'notes' => 'Actividad existente.']);
        $this->rejected(fn () => $this->confirm($p));
        $p = $this->preview();
        $this->planner->submit($this->actor, $this->origin->id);
        $this->rejected(fn () => $this->confirm($p));
        $this->assertSame(1, $this->target->activities()->count());
        $this->assertSame(0, AuditLog::where('action', 'schedule.week_copied')->count());
    }

    public function test_submitted_target_remains_locked_even_with_a_previously_valid_preview(): void
    {
        $this->add();
        $p = $this->preview();
        $this->target->update(['status' => ScheduleStatus::ENVIADO, 'submitted_at' => now()]);
        $this->rejected(fn () => $this->confirm($p));
        $this->rejected(fn () => $this->preview());
        $this->assertSame(0, $this->target->activities()->count());
    }

    public function test_livewire_month_change_clears_source_and_preview_and_confirmation_is_explicit(): void
    {
        $this->add();
        $c = Livewire::actingAs($this->actor)->test(Index::class, ['period' => '2026-10'])
            ->assertSet('copySourceMonth', '2026-10')->set('copySourceMonth', '2026-09')
            ->set('copySourceWeek', (string) $this->origin->weeks[1]->id)->set('copyTargetWeek', (string) $this->target->weeks[1]->id)
            ->call('confirmCopy')->assertHasErrors('copy')->call('reviewCopy')->assertHasNoErrors();
        $this->assertSame(0, $this->target->activities()->count());
        $c->set('copySourceMonth', '2026-10')->assertSet('copySourceWeek', '')->assertSet('copyPreview', null)->assertSet('acknowledgeOmissions', false)
            ->set('copySourceMonth', '2026-09')->set('copySourceWeek', (string) $this->origin->weeks[1]->id)->call('reviewCopy')->call('confirmCopy')->assertHasNoErrors();
        $this->assertSame(1, $this->target->activities()->count());
    }

    public function test_livewire_rejects_a_forged_month_and_week_pair(): void
    {
        $this->add();
        Livewire::actingAs($this->actor)->test(Index::class, ['period' => '2026-10'])
            ->set('copySourceMonth', '2026-09')->set('copySourceWeek', (string) $this->target->weeks[1]->id)->set('copyTargetWeek', (string) $this->target->weeks[2]->id)
            ->call('reviewCopy')->assertHasErrors('copy')->assertSet('copyPreview', null);
        Livewire::actingAs($this->actor)->test(Index::class, ['period' => '2026-10'])
            ->set('copySourceMonth', '2025-01')->set('copySourceWeek', (string) $this->origin->weeks[1]->id)->set('copyTargetWeek', (string) $this->target->weeks[1]->id)
            ->call('reviewCopy')->assertHasErrors('copySourceMonth');
    }

    public function test_copy_across_year_boundary_uses_full_dates_and_preserves_weekdays(): void
    {
        $this->origin = $this->planner->create($this->actor, 2026, 12);
        $this->target = $this->planner->create($this->actor, 2027, 1);
        $this->add('2026-12-07');
        $p = $this->preview();
        $this->assertSame('2027-01-04', $p['rows'][0]['date']);
        $this->assertCount(1, $this->confirm($p));
        Livewire::actingAs($this->actor)->test(Index::class, ['period' => '2027-01'])
            ->set('copySourceMonth', '2026-12')->set('copySourceWeek', (string) $this->origin->weeks[1]->id)->set('copyTargetWeek', (string) $this->target->weeks[2]->id)
            ->call('reviewCopy')->assertSee('07/12/2026')->assertSee('11/01/2027');
    }
}
