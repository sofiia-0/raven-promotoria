<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\PharmacyAssignmentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RavenAssignmentTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassignment_keeps_history_and_updates_visibility(): void
    {
        $one = User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => true]);
        $two = User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => true]);
        $pharmacy = Pharmacy::create(['name' => 'DEMO Establecimiento reasignable']);
        $service = new PharmacyAssignmentService();

        $service->reassign($pharmacy, $one);
        $this->assertSame(1, $one->assignedPharmacies()->count());

        $service->reassign($pharmacy, $two);
        $this->assertSame(0, $one->assignedPharmacies()->count());
        $this->assertSame(1, $two->assignedPharmacies()->count());
        $this->assertSame(2, $pharmacy->assignments()->count());
        $this->assertSame(1, $pharmacy->assignments()->whereNotNull('ended_at')->count());
        $this->assertSame(1, $pharmacy->assignments()->whereNull('ended_at')->count());
    }

    public function test_reassigning_to_same_promoter_does_not_duplicate_assignment(): void
    {
        $one = User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => true]);
        $pharmacy = Pharmacy::create(['name' => 'DEMO Establecimiento']);
        $service = new PharmacyAssignmentService();
        $service->reassign($pharmacy, $one);
        $service->reassign($pharmacy, $one);
        $this->assertSame(1, $pharmacy->assignments()->count());
    }

    public function test_nonpromoter_account_cannot_receive_assignment(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
        $pharmacy = Pharmacy::create(['name' => 'DEMO Establecimiento']);
        $this->expectException(DomainException::class);
        (new PharmacyAssignmentService())->reassign($pharmacy, $admin);
    }

    public function test_inactive_promoter_cannot_receive_assignment(): void
    {
        $invalid = User::factory()->create(['role' => UserRole::PROMOTER, 'is_active' => false]);
        $pharmacy = Pharmacy::create(['name' => 'DEMO Establecimiento']);
        $this->expectException(DomainException::class);
        (new PharmacyAssignmentService())->reassign($pharmacy, $invalid);
    }
}
