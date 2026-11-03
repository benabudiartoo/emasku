<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guests_to_login_and_members_to_ringkasan(): void
    {
        $this->get('/')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/ringkasan');
    }

    public function test_urls_follow_the_https_scheme_forwarded_by_the_proxy(): void
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/')
            ->assertRedirect('https://localhost/login');
    }

    public function test_register_creates_ulid_user_logs_in_and_goes_to_mulai(): void
    {
        $response = $this->post('/register', [
            'name' => 'Sari Wulandari',
            'email' => 'sari@example.test',
            'password' => 'rahasia-aman-1',
        ]);

        $response->assertRedirect('/mulai');
        $this->assertAuthenticated();

        $user = User::where('email', 'sari@example.test')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $user->id);
        $this->assertNotSame('rahasia-aman-1', $user->password);
    }

    public function test_register_rejects_duplicate_email_and_short_password(): void
    {
        User::factory()->create(['email' => 'ada@example.test']);

        $this->post('/register', ['name' => 'X', 'email' => 'ada@example.test', 'password' => 'rahasia-aman-1'])
            ->assertSessionHasErrors('email');
        $this->post('/register', ['name' => 'X', 'email' => 'baru@example.test', 'password' => 'pendek'])
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_login_with_empty_account_goes_to_mulai(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-aman-1']);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia-aman-1'])
            ->assertRedirect('/mulai');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_items_goes_to_ringkasan(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-aman-1']);
        Item::factory()->create(['user_id' => $user->id]);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia-aman-1'])
            ->assertRedirect('/ringkasan');
    }

    public function test_wrong_password_shows_error_toast_and_stays_guest(): void
    {
        $user = User::factory()->create(['password' => 'rahasia-aman-1']);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'salah-total'])
            ->assertRedirect('/login')
            ->assertSessionHas('toast', ['type' => 'error', 'message' => 'Email atau kata sandi salah']);
        $this->assertGuest();
    }

    public function test_logout_ends_session(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_members_cannot_open_login_or_register(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/login')->assertRedirect('/ringkasan');
        $this->get('/register')->assertRedirect('/ringkasan');
    }
}
