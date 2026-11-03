<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_cannot_set_is_admin_and_records_the_first_login(): void
    {
        $this->post('/register', ['name' => 'Cici', 'email' => 'cici@contoh.test', 'password' => 'rahasia-123', 'is_admin' => 1])->assertRedirect('/mulai');

        $user = User::where('email', 'cici@contoh.test')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertNotNull($user->last_login_at);
        $this->get('/admin')->assertNotFound();
    }

    public function test_the_settings_form_cannot_make_anyone_an_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/pengaturan', ['hide_amounts' => true, 'is_admin' => 1])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertTrue($user->fresh()->hide_amounts);
    }

    public function test_mass_assignment_ignores_is_admin_and_last_login(): void
    {
        $user = User::create(['name' => 'Dedi', 'email' => 'dedi@contoh.test', 'password' => 'rahasia-123', 'is_admin' => true, 'last_login_at' => now()]);

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_logging_in_records_the_time(): void
    {
        $user = User::factory()->create(['email' => 'eka@contoh.test', 'last_login_at' => null]);

        $this->post('/login', ['email' => 'eka@contoh.test', 'password' => 'password'])->assertRedirect();

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue($user->fresh()->last_login_at->diffInSeconds(now(), true) < 5);
    }

    public function test_a_failed_login_does_not_record_a_login(): void
    {
        $user = User::factory()->create(['email' => 'fani@contoh.test', 'last_login_at' => null]);

        $this->post('/login', ['email' => 'fani@contoh.test', 'password' => 'salah-sekali']);

        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_the_command_grants_and_revokes_admin(): void
    {
        $user = User::factory()->create(['email' => 'gita@contoh.test']);

        $this->artisan('user:admin', ['email' => 'gita@contoh.test'])->expectsOutputToContain('is now an admin')->assertExitCode(0);
        $this->assertTrue($user->fresh()->is_admin);

        $this->artisan('user:admin', ['email' => 'gita@contoh.test', '--revoke' => true])->expectsOutputToContain('no longer an admin')->assertExitCode(0);
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_the_command_rejects_an_unknown_email_and_changes_nothing(): void
    {
        $user = User::factory()->create(['email' => 'hadi@contoh.test']);

        $this->artisan('user:admin', ['email' => 'tidak-ada@contoh.test'])->expectsOutputToContain('No user with that email')->assertExitCode(1);

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertSame(0, User::where('is_admin', true)->count());
    }
}
