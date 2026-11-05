<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Coordinator\Supervision\Schedules;
use App\Livewire\Coordinator\Supervision\ScheduleShow;
use App\Livewire\Coordinator\Supervision\Visits;
use App\Livewire\Coordinator\Supervision\VisitShow;
use App\Models\MonthlySchedule;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PlannedActivity;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitReason;
use App\Services\SchedulePlanner;
use App\Services\VisitEvidenceStorage;
use App\Services\VisitRecorder;
use App\Services\VisitWithEvidenceRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class CoordinatorSupervisionTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private User $coordinator;

    private User $ana;

    private Pharmacy $pharmacy;

    private VisitReason $reason;

    protected function setUp(): void
    {
        parent::setUp();
        // Jueves 15/10/2026 06:00 en Managua.
        $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'));
        $this->coordinator = User::factory()->coordinator()->create();
        $this->ana = User::factory()->promoter()->create(['name' => 'Ana Promotora']);
        $this->pharmacy = $this->assigned($this->ana, 'Farmacia Central');
        $this->reason = VisitReason::factory()->create(['name' => 'Establecimiento cerrado']);
    }

    private function assigned(User $promoter, string $name): Pharmacy
    {
        $pharmacy = Pharmacy::factory()->create(['name' => $name]);
        PharmacyAssignment::factory()->create(['pharmacy_id' => $pharmacy->id, 'promoter_id' => $promoter->id]);

        return $pharmacy;
    }

    /** Octubre de Ana: visitas el 13 (realizada), 14 (no realizada → reprogramada al 16), 20 (pendiente) y una reunión. */
    private function anaOctober(): MonthlySchedule
    {
        $planner = new SchedulePlanner;
        $schedule = $planner->create($this->ana, 2026, 10);
        foreach (['2026-10-13', '2026-10-14', '2026-10-20'] as $date) {
            $planner->addActivity($this->ana, $schedule->id, ['activity_type' => 'VISITA', 'scheduled_date' => $date, 'pharmacy_id' => $this->pharmacy->id]);
        }
        $planner->addActivity($this->ana, $schedule->id, ['activity_type' => 'REUNION', 'scheduled_date' => '2026-10-05']);
        $planner->submit($this->ana, $schedule->id);

        $recorder = new VisitRecorder;
        $activity = fn (string $date) => PlannedActivity::query()->whereDate('scheduled_date', $date)->where('activity_type', 'VISITA')->sole();
        $recorder->register($this->ana, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'planned_activity_id' => $activity('2026-10-13')->id, 'notes' => 'Exhibición renovada']);
        $recorder->register($this->ana, ['idempotency_key' => (string) Str::uuid(), 'status' => 'NO_REALIZADA', 'planned_activity_id' => $activity('2026-10-14')->id, 'visit_reason_id' => $this->reason->id]);
        $planner->reschedule($this->ana, $activity('2026-10-14')->id, '2026-10-16', $this->reason->id, 'Volver el viernes');
        // Fuera de plan.
        $recorder->register($this->ana, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $this->pharmacy->id]);

        return $schedule;
    }

    /** RF-PLAN: resumen del mes por promotora, incluidas las que no crearon cronograma. */
    public function test_schedules_overview_counts_each_promoter(): void
    {
        $this->anaOctober();
        $bea = User::factory()->promoter()->create(['name' => 'Bea Promotora']);
        (new SchedulePlanner)->create($bea, 2026, 10);
        User::factory()->promoter()->create(['name' => 'Cris Promotora']);
        User::factory()->promoter()->inactive()->create(['name' => 'Dora Inactiva']);

        $this->actingAs($this->coordinator)->get('/coordinator/schedules')->assertOk()->assertSee('Octubre de 2026');

        Livewire::actingAs($this->coordinator)->test(Schedules::class)
            ->assertViewHas('rows', function ($rows) {
                $ana = $rows->firstWhere('promoter.name', 'Ana Promotora');

                return $rows->pluck('promoter.name')->all() === ['Ana Promotora', 'Bea Promotora', 'Cris Promotora']
                    && $ana['schedule']->status->value === 'ENVIADO'
                    && [$ana['planned'], $ana['done'], $ana['not_done'], $ana['pending'], $ana['rescheduled'], $ana['other'], $ana['unplanned']] === [3, 1, 0, 2, 1, 1, 1]
                    && $rows->firstWhere('promoter.name', 'Bea Promotora')['schedule']->isDraft()
                    && $rows->firstWhere('promoter.name', 'Cris Promotora')['schedule'] === null;
            })
            ->assertSee('Sin crear')
            ->assertDontSee('Dora Inactiva')
            ->call('previousMonth')
            ->assertSet('period', '2026-09')
            ->assertViewHas('totals', fn ($t) => $t['planned'] === 0);
    }

    public function test_schedule_detail_shows_progress_and_links_to_visits(): void
    {
        $schedule = $this->anaOctober();

        Livewire::actingAs($this->coordinator)->test(ScheduleShow::class, ['scheduleId' => $schedule->id])
            ->assertSee('Ana Promotora')
            ->assertSee('Realizada')
            ->assertSee('Pendiente')
            ->assertSee('Reprogramada 14/10 → 16/10')
            ->assertSee('Reunión')
            ->assertSee(route('coordinator.visits.show', ['visit' => Visit::query()->where('status', 'REALIZADA')->whereNotNull('planned_activity_id')->value('id')]), false);
    }

    /** RF-VIS: visitas de todo el equipo con filtros en hora de Managua. */
    public function test_visits_list_covers_all_promoters_with_filters(): void
    {
        $this->anaOctober();
        $bea = User::factory()->promoter()->create(['name' => 'Bea Promotora']);
        $beaPharmacy = $this->assigned($bea, 'Farmacia del Norte');
        $beaPharmacy->update(['department' => 'Matagalpa']);
        // 15/10 23:30 en Managua = 16/10 05:30 UTC.
        $this->travelTo(CarbonImmutable::parse('2026-10-16 05:30:00', 'UTC'));
        (new VisitRecorder)->register($bea, ['idempotency_key' => (string) Str::uuid(), 'status' => 'REALIZADA', 'pharmacy_id' => $beaPharmacy->id]);

        $this->actingAs($this->coordinator)->get('/coordinator/visits')->assertOk();

        Livewire::actingAs($this->coordinator)->test(Visits::class)
            ->assertViewHas('visits', fn ($v) => $v->total() === 4)
            ->assertSee('Farmacia del Norte')
            ->set('search', 'bea norte')
            ->assertViewHas('visits', fn ($v) => $v->total() === 1)
            ->set('department', 'León')
            ->assertViewHas('visits', fn ($v) => $v->total() === 0)
            ->set('department', 'Matagalpa')
            ->assertViewHas('visits', fn ($v) => $v->total() === 1)
            ->set('search', '')->set('department', '')
            ->set('promoter', (string) $bea->id)
            ->assertViewHas('visits', fn ($v) => $v->total() === 1)
            ->set('promoter', '')
            ->set('status', 'NO_REALIZADA')
            ->assertViewHas('visits', fn ($v) => $v->total() === 1)
            ->set('status', '')
            ->set('origin', 'fuera')
            ->assertViewHas('visits', fn ($v) => $v->total() === 2)
            ->set('origin', '')
            ->set('from', '2026-10-16')
            ->set('to', '2026-10-16')
            ->assertViewHas('visits', fn ($v) => $v->total() === 0)
            ->set('from', '2026-10-15')
            ->set('to', '2026-10-15')
            ->assertViewHas('visits', fn ($v) => $v->total() === 4);
    }

    public function test_visit_detail_shows_record_and_reschedules(): void
    {
        $this->anaOctober();
        $failed = Visit::query()->where('status', 'NO_REALIZADA')->sole();

        Livewire::actingAs($this->coordinator)->test(VisitShow::class, ['visitId' => $failed->id])
            ->assertSee('Ana Promotora')
            ->assertSee('Establecimiento cerrado')
            ->assertSee('Reprogramaciones')
            ->assertSee('Volver el viernes');

        $this->actingAs($this->coordinator)->get('/coordinator/visits/999999')->assertNotFound();
    }

    /** RNF-SEG: la Coordinadora consulta evidencias; nadie más entra por su ruta. */
    public function test_coordinator_can_view_attached_evidence_read_only(): void
    {
        Storage::fake('visit_evidence');
        $key = (string) Str::uuid();
        $file = (new VisitEvidenceStorage)->upload($this->ana, $key, UploadedFile::fake()->image('foto.jpg', 120, 80));
        $visit = (new VisitWithEvidenceRecorder)->register($this->ana, ['idempotency_key' => $key, 'pharmacy_id' => $this->pharmacy->id, 'status' => 'REALIZADA'], [$file->id]);

        $download = route('coordinator.visits.evidence.download', ['visit' => $visit->id, 'evidence' => $file->id]);
        $preview = route('coordinator.visits.evidence.preview', ['visit' => $visit->id, 'evidence' => $file->id]);

        Livewire::actingAs($this->coordinator)->test(VisitShow::class, ['visitId' => $visit->id])->assertSee('foto.jpg');
        $this->actingAs($this->coordinator)->get($download)->assertOk()->assertDownload('foto.jpg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($preview)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        // Una evidencia de otra visita no se sirve con este ID de visita.
        $this->get(route('coordinator.visits.evidence.download', ['visit' => $visit->id + 1, 'evidence' => $file->id]))->assertNotFound();

        // Promotoras y Administración no usan la ruta de Coordinación; una coordinadora inactiva tampoco.
        $this->actingAs($this->ana)->get($download)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]))->get($download)->assertForbidden();
        $this->actingAs(User::factory()->coordinator()->inactive()->create())->get($download)->assertForbidden();

        // La promotora dueña sigue accediendo por su propia ruta.
        $this->actingAs($this->ana)->get(route('promoter.visits.evidence.download', ['visit' => $visit->id, 'evidence' => $file->id]))->assertOk();
    }

    public function test_supervision_pages_are_coordinator_only(): void
    {
        $schedule = $this->anaOctober();
        $visit = Visit::query()->first();

        foreach (['/coordinator/schedules', '/coordinator/schedules/'.$schedule->id, '/coordinator/visits', '/coordinator/visits/'.$visit->id] as $url) {
            $this->actingAs($this->ana)->get($url)->assertForbidden();
        }
        Livewire::actingAs($this->ana)->test(Visits::class)->assertForbidden();
        Livewire::actingAs($this->ana)->test(VisitShow::class, ['visitId' => $visit->id])->assertForbidden();
    }
}
