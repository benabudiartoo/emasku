<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_see_the_page(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_a_regular_user_gets_not_found_not_forbidden(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertNotFound();
        $this->getJson('/admin')->assertNotFound();
    }

    public function test_an_admin_opens_the_page(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/admin')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/Index'));
    }

    public function test_the_menu_flag_is_true_only_for_admins(): void
    {
        $this->actingAs(User::factory()->create())->get('/menu')
            ->assertInertia(fn (Assert $p) => $p->where('auth.user.is_admin', false));

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/menu')
            ->assertInertia(fn (Assert $p) => $p->where('auth.user.is_admin', true));
    }

    public function test_an_admin_is_otherwise_an_ordinary_owner_who_sees_only_their_own_data(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        \App\Models\Item::factory()->create(['user_id' => $other->id, 'name' => 'Milik orang lain']);

        $this->actingAs($admin)->get('/koleksi')->assertInertia(fn (Assert $p) => $p->where('items', []));
    }
}
