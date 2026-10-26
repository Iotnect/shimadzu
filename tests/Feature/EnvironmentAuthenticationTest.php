<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnvironmentAuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'auth.providers.users.driver' => 'environment',
            'auth.providers.users.username' => 'admin',
            'auth.providers.users.password_hash' => Hash::make('private-admin-password'),
            'session.driver' => 'cookie',
            'cache.default' => 'array',
            'database.default' => 'unavailable',
        ]);
        Auth::forgetGuards();
    }

    public function test_guests_can_view_login_without_any_database_connection(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back')->assertDontSee('Keep me signed in');
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_configured_credentials_create_a_session_without_a_database(): void
    {
        $response = $this->post('/login', ['username' => 'ADMIN', 'password' => 'private-admin-password']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertSame('Administrator', Auth::user()->name);

        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        Auth::forgetGuards();
        $this->app['session']->flush();

        $this->withUnencryptedCookies($cookies)->get('/setting')->assertOk()->assertSee('Appearance');
    }

    public function test_incorrect_passwords_are_rejected_without_querying_a_database(): void
    {
        $this->from('/login')->post('/login', ['username' => 'admin', 'password' => 'wrong-password'])
            ->assertRedirect('/login')->assertSessionHasErrors('username')->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_a_missing_admin_hash_fails_closed(): void
    {
        config(['auth.providers.users.password_hash' => '']);
        Auth::forgetGuards();

        $this->post('/login', ['username' => 'admin', 'password' => 'private-admin-password'])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_unknown_usernames_are_rejected(): void
    {
        $this->post('/login', ['username' => 'other', 'password' => 'private-admin-password'])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_stateless_login_does_not_issue_a_remember_token(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'private-admin-password', 'remember' => '1'])
            ->assertRedirect(route('dashboard'))->assertCookieMissing(Auth::guard()->getRecallerName());
    }

    public function test_changing_the_configured_password_invalidates_the_old_account_identifier(): void
    {
        $provider = Auth::createUserProvider('users');
        $user = $provider->retrieveByCredentials(['username' => 'admin']);
        config(['auth.providers.users.password_hash' => Hash::make('new-admin-password')]);

        $this->assertNull(Auth::createUserProvider('users')->retrieveById($user->getAuthIdentifier()));
    }

    public function test_repeated_invalid_logins_are_still_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['username' => 'admin', 'password' => 'wrong-password']);
        }

        $this->post('/login', ['username' => 'admin', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['username' => 'Too many sign-in attempts. Please try again in one minute.']);
    }

    public function test_an_admin_hash_can_be_generated_without_a_database(): void
    {
        $this->artisan('app:admin-password-hash')
            ->expectsQuestion('Administrator password (at least 12 characters)', 'private-admin-password')
            ->expectsOutputToContain('$2y$')
            ->assertSuccessful();
    }

    public function test_short_admin_passwords_are_rejected(): void
    {
        $this->artisan('app:admin-password-hash')
            ->expectsQuestion('Administrator password (at least 12 characters)', 'short')
            ->expectsOutput('Use a password of at least 12 characters.')
            ->assertFailed();
    }
}
