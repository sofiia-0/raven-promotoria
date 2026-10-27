<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\Users\Index;
use App\Models\User;
use App\Notifications\RavenPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class RavenAccessEmailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_admin_creates_promoter_with_password_link_instead_of_sending_password(): void
    {
        Notification::fake();
        config(['mail.default' => 'array']);

        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('name', 'Promotora Nueva')
            ->set('email', '  NUEVA@RAVEN.TEST  ')
            ->set('role', UserRole::PROMOTER->value)
            ->call('createUser')
            ->assertHasNoErrors()
            ->assertSee('modo de prueba');

        $user = User::where('email', 'nueva@raven.test')->firstOrFail();
        $this->assertSame(UserRole::PROMOTER, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotEmpty($user->password);

        Notification::assertSentTo($user, RavenPasswordResetNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_admin_can_resend_to_active_coordinator(): void
    {
        Notification::fake();
        config(['mail.default' => 'array']);
        $admin = $this->admin();
        $coordinator = User::factory()->create([
            'role' => UserRole::COORDINATOR,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('resendAccess', $coordinator->id)
            ->assertSee('modo de prueba');

        Notification::assertSentTo($coordinator, RavenPasswordResetNotification::class);
    }

    public function test_does_not_send_to_inactive_accounts(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $inactive = User::factory()->create([
            'role' => UserRole::PROMOTER,
            'is_active' => false,
        ]);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('resendAccess', $inactive->id)
            ->assertSee('La cuenta debe estar activa');

        Notification::assertNothingSent();
    }

    public function test_does_not_resend_admin_access_from_user_table(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $otherAdmin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('resendAccess', $otherAdmin->id)
            ->assertSee('cuentas administrativas');

        Notification::assertNothingSent();
    }

    public function test_promoter_cannot_use_admin_users_component_directly(): void
    {
        $promoter = User::factory()->create([
            'role' => UserRole::PROMOTER,
            'is_active' => true,
        ]);

        Livewire::actingAs($promoter)->test(Index::class)->assertForbidden();
    }

    public function test_notification_link_uses_fortify_reset_page(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PROMOTER,
            'is_active' => true,
        ]);
        $message = (new RavenPasswordResetNotification('token-ejemplo'))->toMail($user);

        $this->assertStringContainsString('/reset-password/token-ejemplo', $message->actionUrl);
        $this->assertStringContainsString('email=', $message->actionUrl);
        $this->assertStringContainsString('Raven', $message->subject);
    }
}
