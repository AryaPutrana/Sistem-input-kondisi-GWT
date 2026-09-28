<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_login_success_as_admin(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@test.com']);

        $response = $this->post(route('login.attempt'), [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_success_as_petugas(): void
    {
        $user = User::factory()->petugas()->create(['email' => 'petugas@test.com']);

        $response = $this->post(route('login.attempt'), [
            'email' => 'petugas@test.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->create(['email' => 'user@test.com']);

        $response = $this->post(route('login.attempt'), [
            'email' => 'user@test.com',
            'password' => 'salah-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_rejected_for_unsupported_role(): void
    {
        User::factory()->create([
            'email' => 'role@test.com',
            'role' => 'supervisor',
        ]);

        $response = $this->post(route('login.attempt'), [
            'email' => 'role@test.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_clears_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_with_array_email_is_rejected_without_error(): void
    {
        $response = $this->post(route('login.attempt'), [
            'email' => ['array@test.com'],
            'password' => 'apa-saja',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_with_null_email_is_rejected_without_error(): void
    {
        $response = $this->post(route('login.attempt'), [
            'email' => null,
            'password' => 'apa-saja',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_locks_out_after_max_failed_attempts(): void
    {
        User::factory()->create(['email' => 'lock@test.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.attempt'), [
                'email' => 'lock@test.com',
                'password' => 'salah-password',
            ]);
        }

        $response = $this->post(route('login.attempt'), [
            'email' => 'lock@test.com',
            'password' => 'salah-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $messages = $response->getSession()->get('errors')->getBag('default')->get('email');

        $this->assertStringContainsString('Terlalu banyak percobaan login', $messages[0]);
        $this->assertStringNotContainsString('Email atau password salah', $messages[0]);
    }
}