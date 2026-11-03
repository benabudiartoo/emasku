<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class GoldPriceEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['goldprice.enabled' => true]);
    }

    private function fake(string $goldBody = '{"currency":"USD","name":"Gold","price":3919.5,"symbol":"XAU"}', int $goldStatus = 200): void
    {
        Http::swap(new Factory);
        Http::fake([
            'api.gold-api.com/*' => Http::response($goldBody, $goldStatus),
            'api.frankfurter.dev/*' => Http::response('{"date":"2026-11-03","base":"USD","quote":"IDR","rate":18141}'),
        ]);
    }

    private function calls(): int
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'gold-api.com'))->count();
    }

    public function test_a_guest_is_redirected_to_login_and_nothing_is_fetched(): void
    {
        $this->fake();

        $this->get('/zakat/harga-emas')->assertRedirect('/login');
        Http::assertNothingSent();
    }

    public function test_a_signed_in_owner_gets_the_price_and_where_it_came_from(): void
    {
        $this->fake();
        $this->actingAs(User::factory()->create());

        $response = $this->getJson('/zakat/harga-emas')->assertOk()->assertJsonStructure([
            'price_per_gram', 'currency', 'source', 'fx_source', 'fx_date', 'fetched_at', 'note',
        ]);

        $response->assertJson([
            'price_per_gram' => '2286035.42',
            'currency' => 'IDR',
            'source' => 'Gold-API.com',
            'fx_source' => 'Frankfurter',
            'fx_date' => '2026-11-03',
        ]);
        $this->assertStringContainsString('bukan harga toko emas', $response->json('note'));
    }

    public function test_an_upstream_error_body_never_reaches_the_response(): void
    {
        $this->actingAs(User::factory()->create());

        $this->fake('{"error":"secret detail from upstream"}');
        $failed = $this->getJson('/zakat/harga-emas')->assertStatus(503);
        $this->assertStringNotContainsString('secret detail', $failed->getContent());
    }

    public function test_when_switched_off_it_answers_503_with_a_message_and_calls_no_one(): void
    {
        config(['goldprice.enabled' => false]);
        $this->fake();
        $this->actingAs(User::factory()->create());

        $this->getJson('/zakat/harga-emas')
            ->assertStatus(503)
            ->assertJson(['error' => 'not_configured', 'message' => 'Harga otomatis belum dipasang. Isi harga manual.'])
            ->assertJsonMissingPath('price_per_gram');
        Http::assertNothingSent();
    }

    public function test_a_provider_error_answers_503_with_a_manual_entry_message_and_no_price(): void
    {
        $this->fake('server error', 500);
        $this->actingAs(User::factory()->create());

        $this->getJson('/zakat/harga-emas')
            ->assertStatus(503)
            ->assertJson(['error' => 'unavailable', 'message' => 'Harga tidak bisa diambil sekarang. Isi manual.'])
            ->assertJsonMissingPath('price_per_gram');
    }

    public function test_a_provider_rate_limit_is_reported_as_unavailable_to_the_owner(): void
    {
        $this->fake('{}', 429);
        $this->actingAs(User::factory()->create());

        $this->getJson('/zakat/harga-emas')->assertStatus(503)->assertJson(['error' => 'rate_limited'])->assertJsonMissingPath('price_per_gram');
    }

    public function test_two_owners_within_60_seconds_cause_one_provider_call(): void
    {
        $this->fake();

        $this->actingAs(User::factory()->create())->getJson('/zakat/harga-emas')->assertOk();
        $this->actingAs(User::factory()->create())->getJson('/zakat/harga-emas')->assertOk();

        $this->assertSame(1, $this->calls());
    }

    public function test_an_owner_past_the_rate_limit_gets_429(): void
    {
        config(['goldprice.throttle_per_minute' => 2]);
        RateLimiter::clear('gold-price');
        $this->fake();
        $this->actingAs(User::factory()->create());

        $this->getJson('/zakat/harga-emas')->assertOk();
        $this->getJson('/zakat/harga-emas')->assertOk();
        $this->getJson('/zakat/harga-emas')->assertStatus(429);
        $this->assertSame(1, $this->calls()); // the shared cache still protects the provider
    }

    public function test_the_limit_is_per_owner(): void
    {
        config(['goldprice.throttle_per_minute' => 1]);
        $this->fake();

        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->getJson('/zakat/harga-emas')->assertOk();
        $this->actingAs($a)->getJson('/zakat/harga-emas')->assertStatus(429);
        $this->actingAs($b)->getJson('/zakat/harga-emas')->assertOk();
    }
}
