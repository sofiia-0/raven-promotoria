<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Promoter\Pharmacies\Index;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromoterPharmaciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_a_promoter_can_access_their_pages(): void
    {
        $promoter = User::factory()->create([
            'role' => UserRole::PROMOTER,
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($promoter)->get('/promoter')->assertOk();
        $this->actingAs($promoter)->get('/promoter/pharmacies')->assertOk();
        $this->actingAs($admin)->get('/promoter/pharmacies')->assertForbidden();
    }

    public function test_a_promoter_cannot_see_someone_elses_pharmacies_or_ended_assignments(): void
    {
        $mine = User::factory()->create(['role' => UserRole::PROMOTER]);
        $another = User::factory()->create(['role' => UserRole::PROMOTER]);
        $pharmacyMine = Pharmacy::create(['name' => 'DEMO - Mi farmacia', 'is_active' => true]);
        $pharmacyOther = Pharmacy::create(['name' => 'DEMO - Privada', 'is_active' => true]);
        $pharmacyEnded = Pharmacy::create(['name' => 'DEMO - Asignación terminada', 'is_active' => true]);

        foreach ([[$pharmacyMine, $mine, null], [$pharmacyOther, $another, null], [$pharmacyEnded, $mine, now()]] as [$p, $u, $end]) {
            PharmacyAssignment::create([
                'pharmacy_id' => $p->id,
                'promoter_id' => $u->id,
                'assigned_at' => now()->subDays(2),
                'ended_at' => $end,
            ]);
        }

        Livewire::actingAs($mine)
            ->test(Index::class)
            ->assertSee('DEMO - Mi farmacia')
            ->assertDontSee('DEMO - Privada')
            ->assertDontSee('DEMO - Asignación terminada');
    }

    public function test_search_does_not_bypass_the_promoter_assignment_filter(): void
    {
        $mine = User::factory()->create(['role' => UserRole::PROMOTER]);
        $another = User::factory()->create(['role' => UserRole::PROMOTER]);
        $p = Pharmacy::create(['name' => 'DEMO - Farmacia Ajena', 'city' => 'León']);
        PharmacyAssignment::create([
            'pharmacy_id' => $p->id,
            'promoter_id' => $another->id,
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($mine)
            ->test(Index::class)
            ->set('search', 'Ajena')
            ->assertDontSee('DEMO - Farmacia Ajena');
    }
}
