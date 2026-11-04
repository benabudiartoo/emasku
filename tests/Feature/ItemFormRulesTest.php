<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItemFormRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function bar(array $over = []): array
    {
        return $over + ['kind' => 'bar', 'name' => 'Batangan', 'ownership' => 'own', 'owned_since_certainty' => 'exact',
            'unit_weight_g' => '10', 'quantity' => 1, 'purity_input' => '24'];
    }

    private function ring(array $over = []): array
    {
        return $over + ['kind' => 'jewelry', 'name' => 'Cincin', 'ownership' => 'own', 'owned_since_certainty' => 'exact',
            'gross_weight_g' => '5', 'stone_state' => 'none', 'purity_input' => '18'];
    }

    public function test_the_bar_brand_must_come_from_the_list(): void
    {
        foreach (['Antam', 'Galeri 24', 'Lotus Archi', 'UBS', 'Hartadinata', 'Lainnya'] as $brand) {
            $this->post('/koleksi', $this->bar(['brand' => $brand]))->assertSessionHasNoErrors();
        }
        $this->post('/koleksi', $this->bar(['brand' => 'Merek bebas']))->assertSessionHasErrors('brand');
    }

    public function test_purity_is_always_saved_in_karat_whatever_unit_is_sent(): void
    {
        $this->post('/koleksi', $this->ring(['purity_input' => '18', 'purity_source' => 'permil']))->assertSessionHasNoErrors();

        $item = Item::firstOrFail();
        $this->assertSame('karat', $item->purity_source);
        $this->assertSame('0.750000', $item->purity_fraction);
    }

    public function test_karat_above_24_is_rejected(): void
    {
        $this->post('/koleksi', $this->ring(['purity_input' => '25']))->assertSessionHasErrors('purity_input');
    }

    public function test_an_item_saved_in_permil_is_offered_in_karat_when_edited(): void
    {
        $item = Item::factory()->create(['user_id' => $this->user->id, 'purity_source' => 'permil', 'purity_input' => '750', 'purity_fraction' => '0.750000']);

        $this->get("/koleksi/{$item->id}/edit")->assertInertia(fn (Assert $p) => $p->where('item.purity_karat', '18.0000'));
    }

    public function test_only_jewelry_keeps_a_resale_discount(): void
    {
        $this->post('/koleksi', $this->bar(['resale_discount_percent' => '10']))->assertSessionHasNoErrors();
        $this->post('/koleksi', $this->ring(['resale_discount_percent' => '10']))->assertSessionHasNoErrors();

        $this->assertNull(Item::where('kind', 'bar')->firstOrFail()->resale_discount_percent);
        $this->assertSame('10.00', Item::where('kind', 'jewelry')->firstOrFail()->resale_discount_percent);
    }

    public function test_new_items_are_bought_and_the_removed_fields_are_ignored(): void
    {
        $this->post('/koleksi', $this->ring(['acquisition_method' => 'gift', 'owned_since_note' => 'x']))->assertSessionHasNoErrors();

        $item = Item::firstOrFail();
        $this->assertSame('purchase', $item->acquisition_method);
        $this->assertNull($item->owned_since_note);
    }

    public function test_editing_keeps_the_location_the_form_no_longer_sends(): void
    {
        $place = \App\Models\Location::factory()->create(['user_id' => $this->user->id]);
        $item = Item::factory()->create(['user_id' => $this->user->id, 'location_id' => $place->id]);

        $this->patch("/koleksi/{$item->id}", $this->bar(['name' => 'Diubah']))->assertSessionHasNoErrors();

        $this->assertSame($place->id, $item->fresh()->location_id);
    }
}
