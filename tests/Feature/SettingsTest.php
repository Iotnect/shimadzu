<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/setting')->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_open_appearance_settings_without_production_data(): void
    {
        $this->actingAs(User::factory()->make())
            ->get('/setting')
            ->assertOk()
            ->assertViewIs('settings')
            ->assertSee('MANAGEMENT')
            ->assertSee('Appearance')
            ->assertSee('Dark mode')
            ->assertSee('role="switch"', false)
            ->assertSee('Sign out');
    }
}
