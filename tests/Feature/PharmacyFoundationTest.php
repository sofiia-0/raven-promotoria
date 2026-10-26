<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_promoter_sees_only_their_current_assigned_pharmacies(): void
    {
        $first = User::factory()->create(['role' => UserRole::PROMOTER]);
        $second = User::factory()->create(['role' => UserRole::PROMOTER]);

        $one = Pharmacy::create(['name' => 'DEMO Farmacia Uno']);
        $two = Pharmacy::create(['name' => 'DEMO Farmacia Dos']);

        PharmacyAssignment::create([
            'pharmacy_id' => $one->id,
            'promoter_id' => $first->id,
            'assigned_at' => now()->subDay(),
        ]);
        PharmacyAssignment::create([
            'pharmacy_id' => $two->id,
            'promoter_id' => $second->id,
            'assigned_at' => now()->subDay(),
        ]);

        $this->assertEquals([$one->id], $first->assignedPharmacies()->pluck('pharmacies.id')->all());
        $this->assertEquals([$two->id], $second->assignedPharmacies()->pluck('pharmacies.id')->all());
    }

    public function test_ended_assignments_are_not_returned_as_current(): void
    {
        $promoter = User::factory()->create(['role' => UserRole::PROMOTER]);
        $pharmacy = Pharmacy::create(['name' => 'DEMO Farmacia']);

        $assignment = PharmacyAssignment::create([
            'pharmacy_id' => $pharmacy->id,
            'promoter_id' => $promoter->id,
            'assigned_at' => now()->subWeek(),
        ]);

        $this->assertTrue($promoter->assignedPharmacies()->whereKey($pharmacy->id)->exists());
        $assignment->update(['ended_at' => now()]);
        $this->assertFalse($promoter->assignedPharmacies()->whereKey($pharmacy->id)->exists());
        $this->assertSame(1, $pharmacy->assignments()->count());
    }
}
