<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Services\GoldCalculator;
use App\Services\ResaleEstimate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ResaleEstimateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['goldprice.enabled' => true]);
        $this->user = User::factory()->create();
    }

    /** A 10 g (net) ring at 18K: 7.5 g of pure gold. */
    private function ring(array $over = [], ?User $owner = null): Item
    {
        return Item::factory()->jewelry()->create($over + [
            'user_id' => ($owner ?? $this->user)->id,
            'gross_weight_g' => '10.0000', 'stone_state' => 'none', 'stone_weight_g' => null,
            'purity_source' => 'karat', 'purity_input' => '18', 'purity_fraction' => '0.750000',
            'acquisition_cost' => '10000000.00', 'resale_discount_percent' => '10.00',
        ]);
    }

    private function estimate(Item $item, ?string $price): array
    {
        $calc = app(GoldCalculator::class);

        return app(ResaleEstimate::class)->estimate($item, $calc->netWeight($item), $calc->pureGold($item), $price);
    }

    public function test_the_market_price_wins_when_it_is_higher_than_the_discounted_price(): void
    {
        // discounted = 10,000,000 x 0.9 = 9,000,000; market = 7.5 g x 2,000,000 = 15,000,000
        $r = $this->estimate($this->ring(), '2000000.00');

        $this->assertSame('9000000.00', $r['discounted']);
        $this->assertSame('15000000.00', $r['market']);
        $this->assertSame('15000000.00', $r['estimate']);
        $this->assertSame(ResaleEstimate::BASIS_MARKET, $r['basis']);
        $this->assertNull($r['market_reason']);
    }

    public function test_the_discounted_price_wins_when_it_is_higher_than_the_market_price(): void
    {
        $r = $this->estimate($this->ring(), '1000000.00'); // market = 7,500,000

        $this->assertSame('7500000.00', $r['market']);
        $this->assertSame('9000000.00', $r['estimate']);
        $this->assertSame(ResaleEstimate::BASIS_DISCOUNTED, $r['basis']);
    }

    public function test_equal_prices_count_as_the_discounted_price(): void
    {
        $r = $this->estimate($this->ring(), '1200000.00'); // market = 9,000,000 = discounted

        $this->assertSame('9000000.00', $r['estimate']);
        $this->assertSame(ResaleEstimate::BASIS_DISCOUNTED, $r['basis']);
    }

    public function test_without_a_market_price_only_the_discounted_price_is_given_and_it_says_so(): void
    {
        $r = $this->estimate($this->ring(), null);

        $this->assertSame('9000000.00', $r['discounted']);
        $this->assertNull($r['market']); // not zero, not equal to the discounted price
        $this->assertSame('9000000.00', $r['estimate']);
        $this->assertSame(ResaleEstimate::BASIS_DISCOUNTED_ONLY, $r['basis']);
        $this->assertSame('price_unavailable', $r['market_reason']);
    }

    public function test_an_unknown_purity_means_no_market_price_for_that_item(): void
    {
        $r = $this->estimate($this->ring(['purity_source' => null, 'purity_input' => null, 'purity_fraction' => null]), '9999999.00');

        $this->assertNull($r['market']);
        $this->assertSame(ResaleEstimate::BASIS_DISCOUNTED_ONLY, $r['basis']);
        $this->assertSame('purity_unknown', $r['market_reason']);
        $this->assertSame('9000000.00', $r['estimate']);
    }

    public function test_missing_inputs_give_no_estimate_with_the_reasons_and_never_zero(): void
    {
        $noCost = $this->estimate($this->ring(['acquisition_cost' => null]), '2000000.00');
        $noDiscount = $this->estimate($this->ring(['resale_discount_percent' => null]), '2000000.00');
        $noWeight = $this->estimate($this->ring(['gross_weight_g' => null]), '2000000.00');
        $all = $this->estimate($this->ring(['acquisition_cost' => null, 'resale_discount_percent' => null, 'gross_weight_g' => null]), '2000000.00');

        $this->assertFalse($noCost['estimable']);
        $this->assertSame(['acquisition_cost'], $noCost['missing']);
        $this->assertSame(['resale_discount_percent'], $noDiscount['missing']);
        $this->assertSame(['net_weight'], $noWeight['missing']);
        $this->assertSame(['acquisition_cost', 'resale_discount_percent', 'net_weight'], $all['missing']);
        foreach ([$noCost, $noDiscount, $noWeight, $all] as $r) {
            $this->assertNull($r['estimate']);
            $this->assertNull($r['discounted']);
            $this->assertNull($r['market']);
        }
    }

    public function test_the_edges_of_the_discount(): void
    {
        $zero = $this->estimate($this->ring(['resale_discount_percent' => '0']), null);
        $this->assertSame('10000000.00', $zero['estimate']); // no discount: the price paid

        $full = $this->estimate($this->ring(['resale_discount_percent' => '100']), '2000000.00');
        $this->assertSame('0.00', $full['discounted']);
        $this->assertSame('15000000.00', $full['estimate']); // the market price is higher, so it wins

        $fullNoMarket = $this->estimate($this->ring(['resale_discount_percent' => '100']), null);
        $this->assertSame('0.00', $fullNoMarket['estimate']); // the owner's own 100 percent is a real zero
    }

    public function test_the_result_is_rounded_once_at_the_end(): void
    {
        // 1,000,000.01 x (1 - 0.3333) = 666,700.006667 -> 666,700.01
        $r = $this->estimate($this->ring(['acquisition_cost' => '1000000.01', 'resale_discount_percent' => '33.33']), null);

        $this->assertSame('666700.01', $r['discounted']);
    }

    public function test_the_summary_totals_only_estimable_items_against_their_own_cost(): void
    {
        $this->ring(['name' => 'A pasar lebih tinggi']); // market 15,000,000 at 2,000,000/g; cost 10,000,000
        $this->ring(['name' => 'B potongan lebih tinggi', 'acquisition_cost' => '20000000.00', 'resale_discount_percent' => '5']); // discounted 19,000,000 vs market 15,000,000
        $this->ring(['name' => 'C tanpa potongan', 'resale_discount_percent' => null]); // not part of the view
        $this->ring(['name' => 'D tanpa harga beli', 'acquisition_cost' => null]); // has a discount but cannot be estimated
        $this->ring(['name' => 'Titipan', 'ownership' => 'held_for_others', 'held_for' => 'Paman']);
        $this->ring(['name' => 'Orang lain'], User::factory()->create());

        $items = Item::where('user_id', $this->user->id)->with('units')->get();
        $s = app(ResaleEstimate::class)->summarize($items, app(GoldCalculator::class), '2000000.00');

        $this->assertSame('34000000.00', $s['estimated_total']); // 15,000,000 + 19,000,000
        $this->assertSame('30000000.00', $s['cost']); // 10,000,000 + 20,000,000: only A and B
        $this->assertSame('4000000.00', $s['difference']);
        $this->assertSame('13.3', $s['difference_percent']);
        $this->assertSame(2, $s['items_estimated']);
        $this->assertSame(1, $s['items_using_market']); // only A
        $this->assertSame(1, $s['items_not_estimable']); // only D
    }

    public function test_the_summary_without_any_discount_has_zero_counts_not_invented_totals(): void
    {
        $this->ring(['resale_discount_percent' => null]);

        $s = app(ResaleEstimate::class)->summarize(Item::with('units')->get(), app(GoldCalculator::class), '2000000.00');

        $this->assertSame(0, $s['items_estimated']);
        $this->assertSame(0, $s['items_not_estimable']);
        $this->assertNull($s['difference_percent']);
    }

    // ---- HTTP -------------------------------------------------------------------------------------------------

    /** 3919.5 USD/oz at 18141 IDR/USD = Rp 2.286.035,42 per gram. */
    private function fakePrice(string $goldBody = '{"currency":"USD","name":"Gold","price":3919.5,"symbol":"XAU"}', int $status = 200): void
    {
        Http::swap(new Factory);
        Http::fake([
            'api.gold-api.com/*' => Http::response($goldBody, $status),
            'api.frankfurter.dev/*' => Http::response('{"date":"2026-11-03","base":"USD","quote":"IDR","rate":18141}'),
        ]);
    }

    public function test_the_item_endpoint_returns_the_breakdown_with_the_source(): void
    {
        $this->fakePrice();
        $item = $this->ring();

        $response = $this->actingAs($this->user)->getJson("/koleksi/{$item->id}/perkiraan-jual")->assertOk();

        // market = 7.5 g x 2,286,035.42 = 17,145,265.65 > discounted 9,000,000
        $response->assertJsonPath('estimate.market', '17145265.65');
        $response->assertJsonPath('estimate.discounted', '9000000.00');
        $response->assertJsonPath('estimate.estimate', '17145265.65');
        $response->assertJsonPath('estimate.basis', 'market');
        $response->assertJsonPath('price.source', 'Gold-API.com');
        $response->assertJsonMissingPath('estimate.best_exact');
        $this->assertNull($response->json('price_message'));
        $this->assertStringContainsString('bukan harga toko emas', $response->json('price.note'));
    }

    public function test_the_item_endpoint_keeps_the_discounted_price_when_the_price_fails(): void
    {
        $item = $this->ring();

        foreach ([['server error', 500], ['{}', 429]] as [$body, $status]) {
            Cache::flush();
            $this->fakePrice($body, $status);

            $response = $this->actingAs($this->user)->getJson("/koleksi/{$item->id}/perkiraan-jual")->assertOk();

            $response->assertJsonPath('estimate.discounted', '9000000.00');
            $response->assertJsonPath('estimate.estimate', '9000000.00');
            $response->assertJsonPath('estimate.basis', 'discounted_only');
            $response->assertJsonPath('estimate.market', null);
            $response->assertJsonPath('estimate.market_reason', 'price_unavailable');
            $response->assertJsonPath('price', null);
            $this->assertNotEmpty($response->json('price_message'));
        }
    }

    public function test_the_item_endpoint_is_off_limits_for_other_owners_and_guests(): void
    {
        $this->fakePrice();
        $theirs = $this->ring([], User::factory()->create());

        $this->actingAs($this->user)->getJson("/koleksi/{$theirs->id}/perkiraan-jual")->assertNotFound();

        auth()->logout();
        $this->get("/koleksi/{$theirs->id}/perkiraan-jual")->assertRedirect('/login');
    }

    public function test_the_item_endpoint_is_rate_limited(): void
    {
        config(['goldprice.throttle_per_minute' => 2]);
        RateLimiter::clear('gold-price');
        $this->fakePrice();
        $item = $this->ring();

        $this->actingAs($this->user)->getJson("/koleksi/{$item->id}/perkiraan-jual")->assertOk();
        $this->actingAs($this->user)->getJson("/koleksi/{$item->id}/perkiraan-jual")->assertOk();
        $this->actingAs($this->user)->getJson("/koleksi/{$item->id}/perkiraan-jual")->assertStatus(429);
    }

    public function test_the_value_endpoint_carries_the_resale_block(): void
    {
        $this->fakePrice();
        $this->ring(); // market 17,145,265.65 beats 9,000,000
        $this->ring(['acquisition_cost' => '30000000.00', 'resale_discount_percent' => '5']); // discounted 28,500,000 beats market 17,145,265.65

        $response = $this->actingAs($this->user)->getJson('/ringkasan/nilai')->assertOk();

        $response->assertJsonPath('resale.estimated_total', '45645265.65'); // 17,145,265.65 + 28,500,000
        $response->assertJsonPath('resale.cost', '40000000.00');
        $response->assertJsonPath('resale.difference', '5645265.65');
        $response->assertJsonPath('resale.difference_percent', '14.1');
        $response->assertJsonPath('resale.items_estimated', 2);
        $response->assertJsonPath('resale.items_using_market', 1);
        $response->assertJsonPath('resale.items_not_estimable', 0);
    }

    public function test_the_value_endpoint_still_fails_without_figures_when_the_price_fails(): void
    {
        $this->fakePrice('server error', 500);
        $this->ring();

        $response = $this->actingAs($this->user)->getJson('/ringkasan/nilai')->assertStatus(503);

        $response->assertJsonMissingPath('resale');
        $response->assertJsonMissingPath('total_value');
    }
}
