<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function barPayload(array $over = []): array
    {
        return $over + [
            'kind' => 'bar', 'name' => 'Antam 10 g', 'ownership' => 'own', 'acquisition_method' => 'purchase',
            'owned_since_certainty' => 'exact', 'unit_weight_g' => '10', 'unit_mode' => 'serial', 'serials' => ['A1', 'A2'],
            'purity_source' => 'permil', 'purity_input' => '999,9',
        ];
    }

    private function ringPayload(array $over = []): array
    {
        return $over + [
            'kind' => 'jewelry', 'name' => 'Cincin', 'ownership' => 'own', 'acquisition_method' => 'purchase',
            'owned_since_certainty' => 'exact', 'gross_weight_g' => '8', 'stone_state' => 'known', 'stone_weight_g' => '2',
            'purity_source' => 'permil', 'purity_input' => '750',
        ];
    }

    public function test_serial_bar_stores_one_unit_per_serial_and_decimal_comma(): void
    {
        $this->post('/koleksi', $this->barPayload())->assertSessionHasNoErrors();

        $item = Item::firstOrFail();
        $this->assertSame($this->user->id, $item->user_id);
        $this->assertSame(2, $item->units()->count());
        $this->assertSame('0.999900', $item->purity_fraction);
    }

    public function test_identical_lot_stores_one_unit_with_quantity(): void
    {
        $this->post('/koleksi', $this->barPayload(['unit_mode' => 'lot', 'quantity' => 5]))->assertSessionHasNoErrors();

        $units = Item::firstOrFail()->units;
        $this->assertCount(1, $units);
        $this->assertSame(5, $units[0]->quantity);
    }

    public function test_ring_with_known_stone_shows_4_5_g_on_detail(): void
    {
        $this->post('/koleksi', $this->ringPayload());

        $this->get('/koleksi/'.Item::firstOrFail()->id)->assertInertia(fn (Assert $p) => $p
            ->component('Koleksi/Detail')->where('item.pure_gold_g', '4.5000')->where('item.status', 'complete'));
    }

    public function test_unknown_stone_is_stored_as_null_and_flagged(): void
    {
        $this->post('/koleksi', $this->ringPayload(['stone_state' => 'unknown', 'stone_weight_g' => '3']));

        $item = Item::firstOrFail();
        $this->assertSame('unknown', $item->stone_state);
        $this->assertNull($item->stone_weight_g);
        $this->get('/koleksi/'.$item->id)->assertInertia(fn (Assert $p) => $p
            ->where('item.status', 'needs_data')->where('item.pure_gold_g', null));
    }

    public function test_known_stone_requires_weight(): void
    {
        $this->post('/koleksi', $this->ringPayload(['stone_weight_g' => null]))->assertSessionHasErrors('stone_weight_g');
    }

    public function test_purity_out_of_range_is_rejected(): void
    {
        $this->post('/koleksi', $this->ringPayload(['purity_input' => '1500']))->assertSessionHasErrors('purity_input');
        $this->assertSame(0, Item::count());
    }

    public function test_location_of_another_owner_is_rejected(): void
    {
        $theirs = Location::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->post('/koleksi', $this->barPayload(['location_id' => $theirs->id]))->assertSessionHasErrors('location_id');
    }

    public function test_held_for_others_needs_a_name_and_is_not_counted(): void
    {
        $this->post('/koleksi', $this->barPayload(['ownership' => 'held_for_others']))->assertSessionHasErrors('held_for');
        $this->post('/koleksi', $this->barPayload(['ownership' => 'held_for_others', 'held_for' => 'Paman']))->assertSessionHasNoErrors();
        $this->assertSame('held_for_others', Item::firstOrFail()->ownership);
    }

    public function test_old_collection_keeps_owned_since_with_approximate_certainty(): void
    {
        $this->post('/koleksi', $this->barPayload([
            'old' => true, 'then' => 'another', 'owned_since' => '2015-06-01', 'owned_since_certainty' => 'approximate',
            'haul_start' => '2016-01-01', 'haul_start_certainty' => 'unknown', 'acquisition_method' => 'opening_balance',
        ]))->assertRedirect('/koleksi/lama');

        $item = Item::firstOrFail();
        $this->assertSame('2015-06-01', $item->owned_since->format('Y-m-d'));
        $this->assertSame('approximate', $item->owned_since_certainty);
        $this->assertSame(now()->toDateString(), $item->created_at->toDateString());
        $this->assertSame('2016-01-01', $item->haul_start->format('Y-m-d'));
    }

    public function test_old_collection_requires_a_date_unless_certainty_is_unknown(): void
    {
        $this->post('/koleksi', $this->barPayload(['old' => true]))->assertSessionHasErrors('owned_since');
        $this->post('/koleksi', $this->barPayload(['old' => true, 'owned_since_certainty' => 'unknown']))->assertSessionHasNoErrors();
    }

    public function test_photo_is_stored_on_the_private_disk_for_the_owner(): void
    {
        $this->post('/koleksi', $this->barPayload(['photo' => UploadedFile::fake()->image('emas.png')]))->assertSessionHasNoErrors();

        $attachment = Item::firstOrFail()->attachments()->firstOrFail();
        $this->assertSame($this->user->id, $attachment->user_id);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_update_replaces_units_and_keeps_kind(): void
    {
        $this->post('/koleksi', $this->barPayload());
        $item = Item::firstOrFail();

        $this->patch('/koleksi/'.$item->id, $this->barPayload(['kind' => 'jewelry', 'unit_mode' => 'lot', 'quantity' => 3, 'name' => 'Antam 20 g']))
            ->assertRedirect('/koleksi/'.$item->id);

        $item->refresh();
        $this->assertSame('bar', $item->kind);
        $this->assertSame('Antam 20 g', $item->name);
        $this->assertSame(3, (int) $item->units()->sum('quantity'));
        $this->assertSame(1, $item->units()->count());
    }

    public function test_list_filters_by_kind_and_data_status(): void
    {
        $this->post('/koleksi', $this->barPayload());
        $this->post('/koleksi', $this->ringPayload(['stone_state' => 'unknown']));

        $this->get('/koleksi?jenis=jewelry')->assertInertia(fn (Assert $p) => $p->has('items', 1)->where('items.0.kind', 'jewelry'));
        $this->get('/koleksi?data=kurang')->assertInertia(fn (Assert $p) => $p->has('items', 1)->where('items.0.status', 'needs_data'));
        $this->get('/koleksi?data=lengkap')->assertInertia(fn (Assert $p) => $p->has('items', 1)->where('items.0.kind', 'bar'));
    }

    public function test_list_only_shows_own_items(): void
    {
        Item::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->get('/koleksi')->assertInertia(fn (Assert $p) => $p->has('items', 0)->where('has_any', false));
    }

    public function test_locations_crud_and_new_location_is_flashed(): void
    {
        $this->post('/pengaturan/lokasi', ['name' => 'Brankas rumah', 'kind' => 'safe'])->assertSessionHas('new_location_id');
        $location = Location::firstOrFail();
        $this->assertSame($this->user->id, $location->user_id);

        $this->post('/pengaturan/lokasi', ['name' => 'Brankas rumah', 'kind' => 'safe'])->assertSessionHasErrors('name');

        $this->patch('/pengaturan/lokasi/'.$location->id, ['name' => 'Brankas kamar', 'kind' => 'safe']);
        $this->assertSame('Brankas kamar', $location->refresh()->name);

        $this->post('/koleksi', $this->barPayload(['location_id' => $location->id]));
        $this->delete('/pengaturan/lokasi/'.$location->id)->assertSessionHas('toast.type', 'error');
        $this->assertSame(1, Location::count());

        Item::query()->delete();
        $this->delete('/pengaturan/lokasi/'.$location->id);
        $this->assertSame(0, Location::count());
    }

    public function test_settings_saves_hide_amounts_default(): void
    {
        $this->patch('/pengaturan', ['hide_amounts' => true])->assertSessionHas('toast.message', 'Pengaturan disimpan');
        $this->assertTrue($this->user->refresh()->hide_amounts);
    }

    public function test_pages_render(): void
    {
        foreach (['/koleksi/baru/batangan', '/koleksi/baru/perhiasan', '/koleksi/lama', '/menu', '/pengaturan', '/pengaturan/lokasi'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/koleksi/baru/koin')->assertNotFound();
    }
}
