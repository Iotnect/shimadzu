<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_reset_link_changes_the_password_and_can_only_be_used_once(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'original-password', 'remember_token' => 'previous-token']);

        $this->get('/login')->assertOk()->assertSee('Forgot password?');
        $this->post('/forgot-password', ['email' => strtoupper($user->email)])
            ->assertRedirect(route('password.request'))->assertSessionHas('status');

        $notification = Notification::sent($user, ResetPassword::class)->sole();
        $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))
            ->assertOk()->assertSee('Confirm new password');

        $credentials = [
            'email' => $user->email,
            'token' => $notification->token,
            'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password',
        ];
        $this->post('/reset-password', $credentials)->assertRedirect(route('login'))->assertSessionHas('status');
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->assertNull($user->fresh()->remember_token);

        $this->post('/reset-password', $credentials)->assertSessionHasErrors('email');
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'original-password'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'replacement-password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_reset_request_does_not_reveal_unknown_accounts(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'registered@example.com']);

        $this->post('/forgot-password', ['email' => 'unknown@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an account matches that email, a password reset link will be sent.');

        Notification::assertNothingSent();
    }

    public function test_environment_accounts_do_not_offer_database_password_reset(): void
    {
        config(['auth.providers.users.driver' => 'environment']);

        $this->get('/login')->assertOk()->assertDontSee('Forgot password?');
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'admin@example.com'])->assertNotFound();
    }
}
