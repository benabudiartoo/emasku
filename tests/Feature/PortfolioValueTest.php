<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use App\Services\GoldCalculator;
use App\Services\PortfolioValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Concerns\CreatesZakatCollections;
use Tests\TestCase;

class PortfolioValueTest extends TestCase
{
    use CreatesZakatCollections, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['goldprice.enabled' => true]);
        $this->user = User::factory()->create();
    }

    /** 3919.5 USD/oz at 18141 IDR/USD = Rp 2.286.035,42 per gram. */
    private function fakePrice(string $goldBody = '{"currency":"USD","name":"Gold","price":3919.5,"symbol":"XAU"}', int $status = 200): void
    {
        Http::swap(new Factory);
        Http::fake([
            'api.gold-api.com/*' => Http::response($goldBody, $status),
            'api.frankfurter.dev/*' => Http::response('{"date":"2026-11-03","base":"USD","quote":"IDR","rate":18141}'),
        ]);
    }

    /** A: 99.99 g, cost 100 jt. B: jewelry 4.5 g, no cost. C: 9.999 g, cost 20 jt. */
    private function collection(?User $owner = null): void
    {
        $owner ??= $this->user;
        $this->bars($owner, '10.0000', 10, null, ['name' => 'A', 'acquisition_cost' => '100000000.00']);
        Item::factory()->jewelry()->create(['user_id' => $owner->id, 'name' => 'B', 'acquisition_cost' => null]);
        $this->bars($owner, '10.0000', 1, null, ['name' => 'C', 'acquisition_cost' => '20000000.00']);
    }

    public function test_the_value_is_exact_and_the_comparison_covers_only_items_with_a_cost(): void
    {
        $this->fakePrice();
        $this->collection();

        $response = $this->actingAs($this->user)->getJson('/ringkasan/nilai')->assertOk();

        // All complete items: 114.489 g x 2286035.42
        $response->assertJsonPath('total_value', '261725909.20');
        // Items with a cost (A and C): 109.989 g x 2286035.42 against a cost of 120 000 000
        $response->assertJsonPath('comparison.cost', '120000000.00');
        $response->assertJsonPath('comparison.value', '251438749.81');
        $response->assertJsonPath('comparison.difference', '131438749.81');
        $response->assertJsonPath('comparison.difference_percent', '109.5');
        $response->assertJsonPath('comparison.items_with_cost', 2);
        $response->assertJsonPath('comparison.items_without_cost', 1);
        $response->assertJsonPath('price.price_per_gram', '2286035.42');
        $response->assertJsonPath('price.source', 'Gold-API.com');
        $this->assertStringContainsString('bukan harga toko emas', $response->json('price.note'));
    }

    public function test_without_any_purchase_price_there_is_no_comparison_and_no_zero(): void
    {
        $this->fakePrice();
        $this->bars($this->user, '10.0000', 10, null, ['acquisition_cost' => null]);

        $response = $this->actingAs($this->user)->getJson('/ringkasan/nilai')->assertOk();

        $this->assertNull($response->json('comparison'));
        $this->assertNotNull($response->json('total_value'));
    }

    public function test_a_purchase_price_of_zero_has_a_difference_but_no_percentage(): void
    {
        $service = app(PortfolioValue::class);
        $this->bars($this->user, '10.0000', 1, null, ['acquisition_cost' => '0.00']); // e.g. a gift recorded as 0

        $result = $service->compute(Item::with('units')->get(), '2286035.42');

        $this->assertSame('0.00', $result['comparison']['cost']);
        $this->assertSame('22858068.16', $result['comparison']['difference']); // 9.999 g x 2286035.42
        $this->assertNull($result['comparison']['difference_percent']);
    }

    public function test_held_for_others_incomplete_items_and_other_owners_do_not_count(): void
    {
        $this->fakePrice();
        $this->bars($this->user, '10.0000', 1, null, ['acquisition_cost' => '1000.00']);
        $this->bars($this->user, '100.0000', 5, null, ['ownership' => 'held_for_others', 'held_for' => 'Paman', 'acquisition_cost' => '1.00']);
        Item::factory()->create(['user_id' => $this->user->id, 'unit_weight_g' => null, 'acquisition_cost' => '5.00']);
        $this->bars(User::factory()->create(), '100.0000', 5, null, ['acquisition_cost' => '9.00']);

        $response = $this->actingAs($this->user)->getJson('/ringkasan/nilai')->assertOk();

        $response->assertJsonPath('comparison.items_with_cost', 1);
        $response->assertJsonPath('comparison.cost', '1000.00');
        $response->assertJsonPath('total_value', GoldCalculator::round(bcmul('9.9990', '2286035.42', 10), 2));
    }

    public function test_an_empty_collection_has_a_zero_total_and_no_comparison(): void
    {
        $this->fakePrice();

        $response = $this->actingAs($this->user)->getJson('/ringkasan/nilai')->assertOk();

        $response->assertJsonPath('total_value', '0.00'); // a real total of nothing
        $this->assertNull($response->json('comparison'));
    }

    public function test_a_provider_failure_answers_503_with_a_message_and_no_figures(): void
    {
        $this->collection();

        foreach ([['server error', 500], ['{}', 429], ['{"error":"x"}', 200]] as [$body, $status]) {
            Cache::flush();
            $this->fakePrice($body, $status);

            $response = $this->actingAs($this->user)->getJson('/ringkasan/nilai')->assertStatus(503);

            $this->assertSame('Harga tidak bisa diambil sekarang.', $response->json('message'));
            $response->assertJsonMissingPath('total_value');
            $response->assertJsonMissingPath('comparison');
            $response->assertJsonMissingPath('price');
        }
    }

    public function test_when_switched_off_it_answers_503_and_calls_no_one(): void
    {
        config(['goldprice.enabled' => false]);
        $this->fakePrice();
        $this->collection();

        $this->actingAs($this->user)->getJson('/ringkasan/nilai')
            ->assertStatus(503)
            ->assertJsonPath('error', 'not_configured')
            ->assertJsonMissingPath('total_value');
        Http::assertNothingSent();
    }

    public function test_a_guest_is_redirected_and_nothing_is_fetched(): void
    {
        $this->fakePrice();

        $this->get('/ringkasan/nilai')->assertRedirect('/login');
        Http::assertNothingSent();
    }

    public function test_the_endpoint_is_rate_limited_per_owner(): void
    {
        config(['goldprice.throttle_per_minute' => 2]);
        RateLimiter::clear('gold-price');
        $this->fakePrice();
        $other = User::factory()->create();

        $this->actingAs($this->user)->getJson('/ringkasan/nilai')->assertOk();
        $this->actingAs($this->user)->getJson('/ringkasan/nilai')->assertOk();
        $this->actingAs($this->user)->getJson('/ringkasan/nilai')->assertStatus(429);
        $this->actingAs($other)->getJson('/ringkasan/nilai')->assertOk();
    }
}
