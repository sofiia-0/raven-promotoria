<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Promoter\Dashboard;
use App\Models\Pharmacy;
use App\Models\PharmacyAssignment;
use App\Models\PharmacyChange;
use App\Models\User;
use App\Services\PharmacyAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PromoterDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function promoter(): User
    {
        return User::factory()->create([
            'role' => UserRole::PROMOTER,
            'is_active' => true,
        ]);
    }

    private function pharmacy(string $name, User $promoter, array $details = [], bool $ended = false): Pharmacy
    {
        $pharmacy = Pharmacy::create(array_merge([
            'name' => $name,
            'is_active' => true,
            'category' => 'AAA',
            'department' => 'Managua',
            'address' => 'Calle principal',
        ], $details));

        PharmacyAssignment::create([
            'pharmacy_id' => $pharmacy->id,
            'promoter_id' => $promoter->id,
            'assigned_at' => now()->subDays(2),
            'ended_at' => $ended ? now()->subDay() : null,
        ]);

        return $pharmacy;
    }

    public function test_dashboard_counts_only_active_pharmacies_with_current_assignment(): void
    {
        $mine = $this->promoter();
        $other = $this->promoter();

        $this->pharmacy('Farmacia Mia Uno', $mine, ['address' => null]);
        $this->pharmacy('Farmacia Mia Dos', $mine, ['category' => 'AA']);
        $this->pharmacy('Farmacia Ajena', $other);
        $this->pharmacy('Farmacia Antigua', $mine, [], true);
        $this->pharmacy('Farmacia Inactiva', $mine, ['is_active' => false]);

        Livewire::actingAs($mine)
            ->test(Dashboard::class)
            ->assertViewHas('totalActive', 2)
            ->assertViewHas('departmentsCount', 1)
            ->assertViewHas('missingAddress', 1)
            ->assertSee('Por categoría')
            ->assertSee('Por departamento')
            ->assertDontSee('Farmacia Ajena');
    }

    public function test_recent_changes_only_include_currently_assigned_active_pharmacies(): void
    {
        $mine = $this->promoter();
        $other = $this->promoter();

        $current = $this->pharmacy('Actual Mia', $mine);
        $foreign = $this->pharmacy('Secreta Ajena', $other);
        $old = $this->pharmacy('Ex Asignada', $mine, [], true);

        foreach ([$current, $foreign, $old] as $pharmacy) {
            PharmacyChange::create([
                'pharmacy_id' => $pharmacy->id,
                'actor_id' => $mine->id,
                'before' => ['name' => 'Anterior'],
                'after' => ['name' => $pharmacy->name],
            ]);
        }

        Livewire::actingAs($mine)
            ->test(Dashboard::class)
            ->assertSee('Actual Mia')
            ->assertDontSee('Secreta Ajena')
            ->assertDontSee('Ex Asignada');
    }

    public function test_transfer_updates_dashboard_visibility(): void
    {
        $first = $this->promoter();
        $second = $this->promoter();
        $pharmacy = $this->pharmacy('Farmacia Transferida', $first);

        (new PharmacyAssignmentService())->reassign($pharmacy, $second);

        Livewire::actingAs($first)->test(Dashboard::class)
            ->assertViewHas('totalActive', 0);

        Livewire::actingAs($second)->test(Dashboard::class)
            ->assertViewHas('totalActive', 1);
    }

    public function test_dashboard_has_no_visits_metrics_before_visits_module_exists(): void
    {
        $mine = $this->promoter();

        $this->actingAs($mine)->get('/promoter')->assertOk()
            ->assertSee('Planificación y visitas')
            ->assertSee('módulo de planificación y registro de visitas');
    }

    public function test_admin_cannot_access_promoter_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/promoter')->assertForbidden();
        Livewire::actingAs($admin)->test(Dashboard::class)->assertForbidden();
    }
}
