<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\Users\Index;
use App\Models\User;
use App\Notifications\RavenPasswordResetNotification;
use App\Support\AccessFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\Concerns\RequiresIsolatedPromoterDatabase;
use Tests\TestCase;

class AdminAuthUxTest extends TestCase
{
    use RefreshDatabase, RequiresIsolatedPromoterDatabase {
        RequiresIsolatedPromoterDatabase::beforeRefreshingDatabase insteadof RefreshDatabase;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => true]);
    }

    public function test_existing_password_confirmation_route_has_a_working_view(): void
    {
        $this->actingAs($this->admin())->get('/user/confirm-password')->assertOk();
    }

    public function test_admin_user_list_refresh_rechecks_an_account_that_has_been_disabled(): void
    {
        $actor = $this->admin();
        $component = Livewire::actingAs($actor)->test(Index::class);
        $actor->update(['is_active' => false]);
        $component->call('$refresh')->assertForbidden();
    }

    public function test_edit_normalizes_email_before_unique_validation(): void
    {
        $actor = $this->admin();
        User::factory()->promoter()->create(['email' => 'existing@example.test']);
        $target = User::factory()->promoter()->create(['email' => 'target@example.test']);
        Livewire::actingAs($actor)->test(Index::class)->call('editUser', $target->id)
            ->set('editEmail', 'EXISTING@EXAMPLE.TEST')->call('updateUser')
            ->assertHasErrors(['editEmail' => 'unique']);
        $this->assertSame('target@example.test', $target->fresh()->email);
    }

    public function test_guest_auth_views_have_labels_autocomplete_and_independent_password_toggles(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sistema de gestión de promotoría farmacéutica')
            ->assertSee('autocomplete="username"', false)->assertSee('autocomplete="current-password"', false)
            ->assertSee('aria-controls="password"', false)->assertSee('data-access-submit', false)
            ->assertDontSee('Gestión clara para una operación en movimiento');
        $this->get('/forgot-password')->assertOk()->assertSee('Recuperar contraseña')->assertSee('autocomplete="email"', false);
        $this->get('/reset-password/test-token?email=ficticia@example.test')->assertOk()
            ->assertSee('aria-controls="password_confirmation"', false)->assertSee('autocomplete="new-password"', false);
        $this->get('/user/confirm-password')->assertRedirect(route('login'));
    }

    public function test_successful_login_preserves_role_destinations_and_logout(): void
    {
        foreach ([[UserRole::ADMIN, 'admin.dashboard'], [UserRole::COORDINATOR, 'coordinator.dashboard'], [UserRole::PROMOTER, 'promoter.dashboard']] as [$role, $route]) {
            $user = User::factory()->create(['role' => $role, 'password' => 'Raven-Test-2026!', 'is_active' => true]);
            $this->post('/login', ['email' => $user->email, 'password' => 'Raven-Test-2026!'])->assertRedirect();
            $this->assertAuthenticatedAs($user);
            $this->get('/')->assertRedirect(route($route));
            $this->post('/logout')->assertRedirect();
            $this->assertGuest();
        }
    }

    public function test_incorrect_and_inactive_logins_remain_rejected_without_repopulating_passwords(): void
    {
        $user = User::factory()->promoter()->create(['password' => 'Raven-Test-2026!']);
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'Incorrecta'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->withViewErrors(session('errors')->getBag('default')->messages())->view('auth.login')->assertSee('El correo o la contraseña no son correctos.')->assertDontSee('value="Incorrecta"', false);
        $user->update(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Raven-Test-2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reset_link_request_uses_existing_notification_and_token_broker(): void
    {
        Notification::fake();
        $user = User::factory()->promoter()->create();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password')->assertSessionHas('status');
        Notification::assertSentTo($user, RavenPasswordResetNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->get('/forgot-password')->assertSee('Le hemos enviado un enlace para restablecer su contraseña.');
    }

    public function test_password_reset_keeps_existing_rules_confirmation_and_single_use_tokens(): void
    {
        $user = User::factory()->promoter()->create(['password' => 'Anterior-Prueba-2026!']);
        $oldHash = $user->password;
        $token = Password::createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'corta', 'password_confirmation' => 'corta'])->assertSessionHasErrors('password');
        $this->assertSame($oldHash, $user->fresh()->password);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Nueva-Prueba-2026!', 'password_confirmation' => 'Distinta-2026!'])->assertSessionHasErrors('password');
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Nueva-Prueba-2026!', 'password_confirmation' => 'Nueva-Prueba-2026!'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Nueva-Prueba-2026!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Otra-Prueba-2026!', 'password_confirmation' => 'Otra-Prueba-2026!'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Nueva-Prueba-2026!', $user->fresh()->password));
    }

    public function test_invalid_reset_token_shows_a_recovery_action_without_changing_password(): void
    {
        $user = User::factory()->promoter()->create();
        $before = $user->password;
        $url = '/reset-password/token-invalido?email='.urlencode($user->email);
        $this->from($url)->post('/reset-password', ['token' => 'token-invalido', 'email' => $user->email, 'password' => 'Nueva-Prueba-2026!', 'password_confirmation' => 'Nueva-Prueba-2026!'])->assertSessionHasErrors('email');
        $this->withViewErrors(session('errors')->getBag('default')->messages())->view('auth.reset-password')->assertSee('Solicite uno nuevo')->assertSee(route('password.request'), false);
        $this->assertSame($before, $user->fresh()->password);
    }

    public function test_expired_reset_tokens_remain_invalid(): void
    {
        $user = User::factory()->promoter()->create();
        $before = $user->password;
        $token = Password::createToken($user);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire') + 1;
        $this->travel($minutes)->minutes();
        try {
            $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Nueva-Prueba-2026!', 'password_confirmation' => 'Nueva-Prueba-2026!'])->assertSessionHasErrors('email');
            $this->assertSame($before, $user->fresh()->password);
        } finally {
            $this->travelBack();
        }
    }

    public function test_password_confirmation_keeps_session_security_contract(): void
    {
        $actor = User::factory()->create(['role' => UserRole::ADMIN, 'password' => 'Raven-Test-2026!']);
        $this->actingAs($actor)->from('/user/confirm-password')->post('/user/confirm-password', ['password' => 'Incorrecta'])->assertSessionHasErrors('password');
        $this->assertNull(session('auth.password_confirmed_at'));
        $this->post('/user/confirm-password', ['password' => 'Raven-Test-2026!'])->assertSessionHas('auth.password_confirmed_at');
        $this->assertAuthenticatedAs($actor);
    }

    public function test_dashboard_counts_real_users_and_protects_all_admin_pages(): void
    {
        $admin = $this->admin();
        User::factory()->promoter()->count(2)->create();
        User::factory()->create(['role' => UserRole::COORDINATOR, 'is_active' => false]);
        Livewire::actingAs($admin)->test(Dashboard::class)->assertViewHas('total', 4)->assertViewHas('active', 3)->assertViewHas('inactive', 1);
        foreach ([UserRole::PROMOTER, UserRole::COORDINATOR] as $role) {
            $actor = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($actor)->get('/admin')->assertForbidden();
            $this->get('/admin/users')->assertForbidden();
            Livewire::actingAs($actor)->test(Dashboard::class)->assertForbidden();
            Livewire::actingAs($actor)->test(Index::class)->assertForbidden();
        }
    }

    public function test_user_forms_are_initially_closed_and_creation_editing_are_exclusive(): void
    {
        $actor = $this->admin();
        $target = User::factory()->promoter()->create();
        Livewire::actingAs($actor)->test(Index::class)->assertSet('showCreate', false)->assertSet('editingUserId', null)
            ->assertDontSeeHtml('wire:submit="createUser"')->call('openCreate')->assertSet('showCreate', true)
            ->assertSeeHtml('wire:submit="createUser"')->call('editUser', $target->id)->assertSet('showCreate', false)
            ->assertSet('editingUserId', $target->id)->assertSeeHtml('wire:submit="updateUser"')
            ->call('cancelEdit')->assertSet('editingUserId', null)->call('openCreate')->set('name', 'Borrador ficticio')
            ->call('cancelCreate')->assertSet('showCreate', false)->assertSet('name', '');
    }

    public function test_create_retains_password_link_workflow_and_closes_panel_only_on_success(): void
    {
        Notification::fake();
        $actor = $this->admin();
        $component = Livewire::actingAs($actor)->test(Index::class)->call('openCreate')->call('createUser')
            ->assertHasErrors(['name', 'email'])->assertSet('showCreate', true)->assertSee('Complete el campo nombre.');
        $component->set('name', 'Nueva cuenta ficticia')->set('email', '  NUEVA@EXAMPLE.TEST  ')->set('role', 'coordinator')
            ->call('createUser')->assertHasNoErrors()->assertSet('showCreate', false);
        $user = User::where('email', 'nueva@example.test')->firstOrFail();
        $this->assertSame(UserRole::COORDINATOR, $user->role);
        Notification::assertSentTo($user, RavenPasswordResetNotification::class);
        $this->assertNotEmpty($user->password);
    }

    public function test_admin_cannot_create_admins_edit_protected_accounts_or_send_inactive_access(): void
    {
        Notification::fake();
        $actor = $this->admin();
        $target = User::factory()->promoter()->create(['is_active' => false]);
        $component = Livewire::actingAs($actor)->test(Index::class)->call('openCreate')
            ->set('name', 'Cuenta ficticia')->set('email', 'ficticia@example.test')->set('role', 'admin')->call('createUser')->assertHasErrors('role');
        $component->call('cancelCreate')->call('editUser', $actor->id)->assertSet('editingUserId', null)
            ->call('toggleActive', $actor->id)->call('resendAccess', $target->id);
        $this->assertTrue($actor->fresh()->is_active);
        Notification::assertNothingSent();
        $component->set('editingUserId', $actor->id)->call('updateUser')->assertForbidden();
    }

    public function test_editing_activation_and_search_pagination_preserve_existing_business_rules(): void
    {
        $actor = $this->admin();
        User::factory()->promoter()->count(12)->create();
        $target = User::factory()->promoter()->create(['name' => 'Objetivo de prueba', 'email' => 'objetivo@example.test']);
        $component = Livewire::actingAs($actor)->test(Index::class)->call('editUser', $target->id)
            ->set('editName', 'Nombre actualizado')->set('editEmail', 'ACTUALIZADO@EXAMPLE.TEST')->set('editRole', 'coordinator')
            ->call('updateUser')->assertHasNoErrors()->assertSet('editingUserId', null);
        $this->assertSame('actualizado@example.test', $target->fresh()->email);
        $this->assertSame(UserRole::COORDINATOR, $target->fresh()->role);
        $component->call('toggleActive', $target->id);
        $this->assertFalse($target->fresh()->is_active);
        $component->call('toggleActive', $target->id)->call('nextPage')->set('search', 'actualizado@example.test')
            ->assertSee('Nombre actualizado')->assertSee('1 cuenta encontrada');
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_auth_feedback_is_local_to_presentation_and_keeps_unknown_messages(): void
    {
        $this->assertSame('La contraseña no es correcta.', AccessFeedback::message('The provided password was incorrect.'));
        $this->assertSame('Las contraseñas no coinciden.', AccessFeedback::message('The password field confirmation does not match.'));
        $this->assertSame('Utilice al menos 8 caracteres.', AccessFeedback::message('The password field must be at least 8 characters.'));
        $this->assertStringContainsString('30 segundos', AccessFeedback::message('Too many login attempts. Please try again in 30 seconds.'));
        $this->assertSame('Mensaje específico ya traducido.', AccessFeedback::message('Mensaje específico ya traducido.'));
    }
}
