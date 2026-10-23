<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_can_view_the_shimadzu_login_form(): void
    {
        $this->get('/login')->assertOk()->assertSee('SHIMADZU')->assertSee('Welcome back')->assertSee('name="username"', false);
    }

    public function test_correct_credentials_create_an_authenticated_session(): void
    {
        $user = User::factory()->create(['username' => 'admin', 'password' => 'admin123']);

        $this->post('/login', ['username' => 'ADMIN', 'password' => 'admin123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_do_not_authenticate_or_flash_the_password(): void
    {
        User::factory()->create(['username' => 'admin', 'password' => 'admin123']);

        $this->from('/login')->post('/login', ['username' => 'admin', 'password' => 'incorrect'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => 'The username or password is incorrect.'])
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session_and_protects_the_dashboard_again(): void
    {
        $user = User::factory()->create(['username' => 'admin']);

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_dashboard_data_is_not_available_to_guests(): void
    {
        $this->getJson('/dashboard/data?date=2026-05-26')->assertUnauthorized();
    }

    public function test_station_pages_and_data_are_not_available_to_guests(): void
    {
        $this->get('/stations/St1')->assertRedirect(route('login'));
        $this->getJson('/stations/St1/data?date=2026-05-26')->assertUnauthorized();
    }

    public function test_authenticated_users_are_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_login_requires_username_and_password(): void
    {
        $this->post('/login')->assertSessionHasErrors(['username', 'password']);
    }

    public function test_login_throttles_repeated_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['username' => 'missing', 'password' => 'incorrect']);
        }

        $this->post('/login', ['username' => 'missing', 'password' => 'incorrect'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username' => 'Too many sign-in attempts. Please try again in one minute.']);

        $this->assertGuest();
    }

    public function test_remember_option_sets_a_remember_token(): void
    {
        $user = User::factory()->create(['username' => 'admin', 'password' => 'admin123', 'remember_token' => null]);

        $this->post('/login', ['username' => 'admin', 'password' => 'admin123', 'remember' => '1'])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_create_admin_command_stores_a_hashed_password(): void
    {
        $this->artisan('app:create-admin', ['username' => 'admin', '--password' => 'admin123'])->assertExitCode(0);

        $admin = User::query()->where('username', 'admin')->sole();
        $this->assertTrue(Hash::check('admin123', $admin->password));
        $this->assertNotSame('admin123', $admin->password);
    }
}
