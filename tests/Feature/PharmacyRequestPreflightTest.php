<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\PharmacyRequest;
use App\Models\User;
use App\Services\PharmacyRequestPreflight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class PharmacyRequestPreflightTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private function request(array $attributes = []): PharmacyRequest
    {
        return PharmacyRequest::create($attributes + ['promoter_id' => User::factory()->promoter()->create()->id, 'type' => 'alta', 'status' => 'pending', 'data' => ['name' => 'Solicitud de prueba'], 'reason' => 'Motivo de prueba']);
    }

    public function test_valid_rows_pass_and_command_is_read_only(): void
    {
        $this->request();
        $this->request(['type' => 'baja', 'pharmacy_id' => Pharmacy::factory()->create()->id]);
        $this->request(['status' => 'approved', 'reviewed_by' => User::factory()->coordinator()->create()->id, 'reviewed_at' => now()]);
        $before = DB::table('pharmacy_requests')->orderBy('id')->get()->toJson();
        $this->assertSame(0, Artisan::call('raven:pharmacy-requests-preflight'));
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($result['can_migrate']);
        $this->assertSame(3, $result['checked']);
        $this->assertSame($before, DB::table('pharmacy_requests')->orderBy('id')->get()->toJson());
    }

    public function test_conflicting_historical_rows_are_reported_not_transformed(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('MySQL already rejects these invalid fixtures through the CHECK constraints.');
        }
        $this->request(['type' => 'invalido', 'status' => 'pendiente']);
        $this->request(['status' => 'approved']);
        $this->request(['status' => 'pending', 'reviewed_by' => User::factory()->coordinator()->create()->id, 'reviewed_at' => now()]);
        $this->request(['status' => 'rejected', 'reviewed_by' => User::factory()->coordinator()->create()->id, 'reviewed_at' => now(), 'review_note' => null]);
        $this->request(['type' => 'baja', 'pharmacy_id' => null]);
        $invalidDate = $this->request(['status' => 'approved', 'reviewed_by' => User::factory()->coordinator()->create()->id]);
        DB::table('pharmacy_requests')->where('id', $invalidDate->id)->update(['reviewed_at' => '0000-00-00 00:00:00']);
        $before = DB::table('pharmacy_requests')->orderBy('id')->get()->toJson();
        $result = (new PharmacyRequestPreflight)->inspect();
        $this->assertFalse($result['can_migrate']);
        $reasons = array_merge(...array_column($result['conflicts'], 'reasons'));
        foreach (['invalid_type', 'invalid_status', 'resolved_without_reviewer_or_date', 'pending_with_review_metadata', 'rejection_without_note', 'baja_without_existing_pharmacy', 'invalid_review_date'] as $reason) {
            $this->assertContains($reason, $reasons);
        }
        $this->assertSame(1, Artisan::call('raven:pharmacy-requests-preflight'));
        $this->assertSame($before, DB::table('pharmacy_requests')->orderBy('id')->get()->toJson());
    }

    public function test_short_historical_rejection_note_is_preserved_and_flagged_for_review(): void
    {
        $request = $this->request(['status' => 'rejected', 'reviewed_by' => User::factory()->coordinator()->create()->id, 'reviewed_at' => now(), 'review_note' => 'x']);
        $result = (new PharmacyRequestPreflight)->inspect();
        $this->assertTrue($result['can_migrate']);
        $this->assertSame([['id' => $request->id, 'reason' => 'rejection_note_shorter_than_current_rule']], $result['warnings']);
        $this->assertSame('x', $request->fresh()->review_note);
    }
}
