<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Services\Export\CollectionReport;
use App\Services\ItemPresenter;
use App\Services\GoldCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class PurchaseStoreTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function ringPayload(array $over = []): array
    {
        return $over + [
            'kind' => 'jewelry', 'name' => 'Gelang kaku putih', 'ownership' => 'own', 
            'owned_since_certainty' => 'exact', 'gross_weight_g' => '7', 'stone_state' => 'known', 'stone_weight_g' => '0,92',
            'purity_input' => '17', 'acquisition_cost' => '4895000',
        ];
    }

    public function test_the_store_and_the_discount_are_saved_and_shown(): void
    {
        $this->post('/koleksi', $this->ringPayload(['purchase_store' => '  Toko Emas Mulia Gold Palembang ', 'resale_discount_percent' => '12,5']))
            ->assertSessionHasNoErrors();

        $item = Item::firstOrFail();
        $this->assertSame('Toko Emas Mulia Gold Palembang', $item->purchase_store); // trimmed
        $this->assertSame('12.50', $item->resale_discount_percent);

        $this->get("/koleksi/{$item->id}")->assertInertia(fn (Assert $p) => $p
            ->where('item.purchase_store', 'Toko Emas Mulia Gold Palembang')
            ->where('item.resale_discount_percent', '12.50'));
    }

    public function test_the_store_may_be_left_empty(): void
    {
        $this->post('/koleksi', $this->ringPayload())->assertSessionHasNoErrors();

        $this->assertNull(Item::firstOrFail()->purchase_store);
    }

    public function test_a_store_longer_than_120_characters_is_rejected(): void
    {
        $this->post('/koleksi', $this->ringPayload(['purchase_store' => str_repeat('a', 121)]))->assertSessionHasErrors('purchase_store');
        $this->post('/koleksi', $this->ringPayload(['purchase_store' => str_repeat('a', 120)]))->assertSessionHasNoErrors();
    }

    public function test_the_discount_must_be_between_0_and_100(): void
    {
        foreach (['-1', '100,01', '150', 'abc'] as $bad) {
            $this->post('/koleksi', $this->ringPayload(['resale_discount_percent' => $bad]))->assertSessionHasErrors('resale_discount_percent');
        }
        foreach (['0', '100', '15'] as $ok) {
            $this->post('/koleksi', $this->ringPayload(['resale_discount_percent' => $ok]))->assertSessionHasNoErrors();
        }
    }

    public function test_price_per_gram_is_cost_over_net_weight_rounded_once(): void
    {
        // Net weight 7 - 0.92 = 6.08 g; Rp 4.895.000 / 6.08 = 805098.684...
        $this->post('/koleksi', $this->ringPayload())->assertSessionHasNoErrors();

        $this->get('/koleksi/'.Item::firstOrFail()->id)->assertInertia(fn (Assert $p) => $p
            ->where('item.net_g', '6.0800')
            ->where('item.price_per_gram', '805098.68'));
    }

    public function test_price_per_gram_is_null_not_zero_when_cost_or_weight_is_unknown(): void
    {
        $calc = app(GoldCalculator::class);
        $noCost = Item::factory()->jewelry()->create(['user_id' => $this->user->id, 'acquisition_cost' => null]);
        $noWeight = Item::factory()->jewelry()->create(['user_id' => $this->user->id, 'gross_weight_g' => null, 'acquisition_cost' => '1000000']);
        $zeroCost = Item::factory()->jewelry()->create(['user_id' => $this->user->id, 'acquisition_cost' => '0.00']);

        $this->assertNull(ItemPresenter::present($noCost, $calc)['price_per_gram']);
        $this->assertNull(ItemPresenter::present($noWeight, $calc)['price_per_gram']);
        $this->assertSame('0.00', ItemPresenter::present($zeroCost, $calc)['price_per_gram']); // a recorded cost of zero is a real zero
    }

    public function test_the_discounted_price_follows_the_discount_and_is_null_without_the_inputs(): void
    {
        $calc = app(GoldCalculator::class);
        $with = Item::factory()->jewelry()->create(['user_id' => $this->user->id, 'acquisition_cost' => '2000000', 'resale_discount_percent' => '15']);
        $noDiscount = Item::factory()->jewelry()->create(['user_id' => $this->user->id, 'acquisition_cost' => '2000000', 'resale_discount_percent' => null]);
        $noCost = Item::factory()->jewelry()->create(['user_id' => $this->user->id, 'acquisition_cost' => null, 'resale_discount_percent' => '15']);
        $zeroPct = Item::factory()->jewelry()->create(['user_id' => $this->user->id, 'acquisition_cost' => '2000000', 'resale_discount_percent' => '0']);

        $this->assertSame('1700000.00', ItemPresenter::present($with, $calc)['discounted_price']);
        $this->assertNull(ItemPresenter::present($noDiscount, $calc)['discounted_price']);
        $this->assertNull(ItemPresenter::present($noCost, $calc)['discounted_price']);
        $this->assertSame('2000000.00', ItemPresenter::present($zeroPct, $calc)['discounted_price']);
    }

    public function test_the_collection_page_lists_the_owners_stores_and_filters_by_store(): void
    {
        $this->bars($this->user, '10.0000', 1, null, ['name' => 'Di Mulia', 'purchase_store' => 'Mulia']);
        $this->bars($this->user, '10.0000', 1, null, ['name' => 'Di Semar', 'purchase_store' => 'Semar']);
        $this->bars($this->user, '10.0000', 1, null, ['name' => 'Tanpa toko']);
        $this->bars(User::factory()->create(), '10.0000', 1, null, ['name' => 'Orang lain', 'purchase_store' => 'Toko Rahasia']);

        $this->get('/koleksi')->assertInertia(fn (Assert $p) => $p->where('stores', ['Mulia', 'Semar'])->has('items', 3));

        $this->get('/koleksi?toko=Mulia')->assertInertia(fn (Assert $p) => $p
            ->has('items', 1)->where('items.0.name', 'Di Mulia')->where('filters.toko', 'Mulia'));
    }

    public function test_the_collection_list_carries_the_purchase_price_of_each_item_and_null_when_unknown(): void
    {
        $this->bars($this->user, '10.0000', 1, null, ['name' => 'Dengan harga', 'acquisition_cost' => '4895000.00']);
        $this->bars($this->user, '10.0000', 1, null, ['name' => 'Tanpa harga', 'acquisition_cost' => null]);

        $this->get('/koleksi')->assertInertia(fn (Assert $p) => $p->where('items', function ($items) {
            $by = collect($items)->keyBy('name');

            return $by['Dengan harga']['acquisition_cost'] === '4895000.00' && $by['Tanpa harga']['acquisition_cost'] === null;
        }));
    }

    public function test_the_export_has_the_store_columns_and_a_per_store_summary(): void
    {
        $this->bars($this->user, '10.0000', 1, null, ['name' => 'A', 'purchase_store' => 'Signature CCM', 'acquisition_cost' => '7000000', 'resale_discount_percent' => '10']);
        $this->bars($this->user, '20.0000', 1, null, ['name' => 'B', 'purchase_store' => ' Signature CCM ', 'acquisition_cost' => '3000000']);
        $this->bars($this->user, '5.0000', 1, null, ['name' => 'C', 'purchase_store' => 'Bagong', 'acquisition_cost' => null]);
        $this->bars($this->user, '1.0000', 1, null, ['name' => 'D']); // no store
        $this->bars($this->user, '100.0000', 1, null, ['name' => 'Titipan', 'purchase_store' => 'Signature CCM', 'ownership' => 'held_for_others', 'held_for' => 'Paman', 'acquisition_cost' => '999']);

        $report = app(CollectionReport::class)->build($this->user->id, []);
        $columns = $report->sections[0]->columns;
        $a = collect($report->sections[0]->rows)->firstWhere(0, 'A');

        $this->assertSame(['Toko', 'Harga beli', 'Harga per gram', 'Potongan', 'Harga setelah potongan'], [$columns[7], $columns[8], $columns[9], $columns[10], $columns[11]]);
        $this->assertSame('Signature CCM', $a[7]);
        $this->assertSame('Rp 7.000.000', $a[8]);
        $this->assertSame('Rp 700.000', $a[9]); // 7,000,000 / 10 g
        $this->assertSame('10%', $a[10]);
        $this->assertSame('Rp 6.300.000', $a[11]);

        $summary = $report->sections[1];
        $this->assertSame('Rekap per toko', $summary->heading);
        $this->assertSame([
            ['Bagong', '1', '5,00', '', ''], // no cost recorded: empty, not Rp 0
            ['Signature CCM', '2', '30,00', 'Rp 10.000.000', 'Rp 333.333,33'], // held-for-others left out; 10,000,000 / 30 g
            ['Tanpa toko', '1', '1,00', '', ''], // the unnamed store is last
        ], $summary->rows);
    }

    public function test_the_export_filter_by_store_is_named_in_the_report(): void
    {
        $this->bars($this->user, '10.0000', 1, null, ['name' => 'A', 'purchase_store' => 'Mulia']);
        $this->bars($this->user, '10.0000', 1, null, ['name' => 'B', 'purchase_store' => 'Semar']);

        $report = app(CollectionReport::class)->build($this->user->id, ['toko' => 'Mulia']);

        $this->assertSame('Toko Mulia', $report->facts['Filter']);
        $this->assertSame(['A'], array_column($report->sections[0]->rows, 0));
    }

    public function test_the_store_and_the_discount_cannot_be_set_on_someone_elses_item(): void
    {
        $theirs = $this->bars(User::factory()->create(), '10.0000', 1, null, ['purchase_store' => 'Asli']);

        $this->patch("/koleksi/{$theirs->id}", $this->ringPayload(['purchase_store' => 'Diubah']))->assertNotFound();

        $this->assertSame('Asli', $theirs->fresh()->purchase_store);
    }
}
