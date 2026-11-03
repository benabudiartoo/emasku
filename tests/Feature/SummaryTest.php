<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function ring(array $over = []): Item
    {
        return Item::factory()->jewelry()->create($over + ['user_id' => $this->user->id]);
    }

    public function test_empty_account_gets_a_call_to_start(): void
    {
        $this->get('/ringkasan')->assertInertia(fn (Assert $p) => $p->component('Ringkasan/Index')->where('has_any', false)->where('active_count', 0));
        $this->get('/mulai')->assertOk();
    }

    public function test_totals_exclude_incomplete_and_flag_them(): void
    {
        $this->ring(); // 4.5 g
        $this->ring(['name' => 'Gelang', 'stone_state' => 'unknown', 'stone_weight_g' => null]);

        $this->get('/ringkasan')->assertInertia(fn (Assert $p) => $p
            ->where('pure_gold_g', '4.5000')->where('active_count', 2)->where('complete', 1)
            ->has('incomplete', 1)->where('incomplete.0.name', 'Gelang')->where('incomplete.0.missing', ['stone_weight_g']));
    }

    public function test_held_for_others_and_other_owners_are_not_counted(): void
    {
        $this->ring();
        $this->ring(['ownership' => 'held_for_others', 'held_for' => 'Paman']);
        Item::factory()->jewelry()->create(['user_id' => User::factory()->create()->id]);

        $this->get('/ringkasan')->assertInertia(fn (Assert $p) => $p->where('pure_gold_g', '4.5000')->where('active_count', 1));
    }

    public function test_pure_gold_detail_lists_contributing_items_and_splits_by_kind(): void
    {
        $this->ring();
        Item::factory()->create(['user_id' => $this->user->id]); // bar 10 g x 0.9999, needs a unit
        $bar = Item::where('kind', 'bar')->firstOrFail();
        $bar->units()->create(['serial_number' => null, 'quantity' => 2]);
        $this->ring(['name' => 'Tanpa batu info', 'stone_state' => 'unknown', 'stone_weight_g' => null]);

        $this->get('/ringkasan/rincian/emas-murni')->assertInertia(fn (Assert $p) => $p
            ->component('Ringkasan/Rincian')
            ->where('total_g', '24.4980')->where('bars_g', '19.9980')->where('jewelry_g', '4.5000')
            ->has('items', 2)->has('incomplete', 1));
    }

    public function test_item_count_detail_lists_every_owned_item(): void
    {
        $this->ring();
        $this->ring(['stone_state' => 'unknown', 'stone_weight_g' => null]);

        $this->get('/ringkasan/rincian/barang')->assertInertia(fn (Assert $p) => $p->has('items', 2));
    }

    public function test_unknown_metric_is_404(): void
    {
        $this->get('/ringkasan/rincian/zakat')->assertNotFound();
    }

    public function test_guests_cannot_see_the_summary(): void
    {
        auth()->logout();
        $this->get('/ringkasan')->assertRedirect('/login');
    }

    public function test_demo_seeder_creates_fictional_data_once(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $demo = User::where('email', 'demo@contoh.test')->firstOrFail();
        $this->assertSame(7, Item::where('user_id', $demo->id)->count());
        $this->assertSame(1, Item::where('user_id', $demo->id)->where('ownership', 'held_for_others')->count());
    }

    public function test_demo_seeder_refuses_production(): void
    {
        $this->app['env'] = 'production';
        (new DemoSeeder)->run(); // called directly: `db:seed` has its own production prompt

        $this->assertNull(User::where('email', 'demo@contoh.test')->first());
    }
}
