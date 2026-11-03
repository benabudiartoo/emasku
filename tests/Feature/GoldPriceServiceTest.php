<?php

namespace Tests\Feature;

use App\Services\GoldPrice\GoldPriceException;
use App\Services\GoldPrice\GoldPriceService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoldPriceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['goldprice.enabled' => true]);
    }

    /** Http::fake() stacks stubs and the first match wins, so start from a clean client each time. */
    private function fake(mixed $stubs = null): void
    {
        Http::swap(new Factory);
        Http::fake($stubs);
    }

    private function gold(string $body, int $status = 200): array
    {
        return ['api.gold-api.com/*' => Http::response($body, $status)];
    }

    /** gold-api.com quotes USD per troy ounce. */
    private function goldOk(string $ounce = '3919.5'): array
    {
        return $this->gold('{"currency":"USD","currencySymbol":"$","exchangeRate":1.0,"name":"Gold","price":'.$ounce.',"symbol":"XAU","updatedAt":"2026-11-03T06:24:26Z"}');
    }

    private function fxOk(string $rate = '18141'): array
    {
        return ['api.frankfurter.dev/*' => Http::response('{"date":"2026-11-03","base":"USD","quote":"IDR","rate":'.$rate.'}')];
    }

    private function quote(): \App\Services\GoldPrice\PriceQuote
    {
        return app(GoldPriceService::class)->idrPerGram();
    }

    private function assertFailsWith(string $reason): void
    {
        try {
            $this->quote();
            $this->fail("Expected the lookup to fail with {$reason}");
        } catch (GoldPriceException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    private function sentTo(string $host): int
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), $host))->count();
    }

    public function test_conversion_is_exact(): void
    {
        $this->fake($this->goldOk() + $this->fxOk());

        $q = $this->quote();

        // 3919.5 USD/oz / 31.1034768 g/oz = 126.0148511757 USD/g; x 18141 = 2286035.4151783737
        $this->assertSame('2286035.42', $q->pricePerGram);
        $this->assertSame('126.0148511757', $q->usdPerGram);
        $this->assertSame('18141', $q->fxRate);
        $this->assertSame('2026-11-03', $q->fxDate);
        $this->assertSame('Gold-API.com', $q->source);
        $this->assertSame('Frankfurter', $q->fxSource);
        $this->assertNotEmpty($q->fetchedAt);
    }

    public function test_numbers_are_read_as_text_not_floats(): void
    {
        // A long decimal that a float would mangle stays exact; a quoted number works too.
        $this->fake($this->gold('{"symbol":"XAU","currency":"USD","price":"3919.50000000000123"}') + $this->fxOk());

        $this->assertSame(bcdiv('3919.50000000000123', '31.1034768', 10), $this->quote()->usdPerGram);
    }

    public function test_the_right_endpoints_are_called_without_any_key(): void
    {
        $this->fake($this->goldOk() + $this->fxOk());
        $this->quote();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.gold-api.com/price/XAU') && ! $r->hasHeader('X-API-KEY'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/rate/usd/idr') && ! $r->hasHeader('X-API-KEY'));
    }

    public function test_gold_is_cached_for_60_seconds_and_called_again_after(): void
    {
        $this->fake($this->goldOk() + $this->fxOk());

        $this->quote();
        $this->quote();
        $this->assertSame(1, $this->sentTo('gold-api.com'));

        $this->travel(59)->seconds();
        $this->quote();
        $this->assertSame(1, $this->sentTo('gold-api.com'));

        $this->travel(2)->seconds(); // 61 s since the first call
        $this->quote();
        $this->assertSame(2, $this->sentTo('gold-api.com'));
    }

    public function test_the_exchange_rate_is_cached_for_six_hours(): void
    {
        $this->fake($this->goldOk() + $this->fxOk());

        $this->quote();
        $this->travel(5)->hours();
        $this->quote();
        $this->assertSame(1, $this->sentTo('frankfurter.dev'));

        $this->travel(2)->hours(); // 7 h in total
        $this->quote();
        $this->assertSame(2, $this->sentTo('frankfurter.dev'));
    }

    public function test_an_expired_value_is_never_served_when_the_provider_then_fails(): void
    {
        $this->fake($this->goldOk() + $this->fxOk());
        $this->quote();

        $this->travel(61)->seconds();
        $this->fake($this->gold('server error', 500) + $this->fxOk());

        $this->assertFailsWith(GoldPriceException::UNAVAILABLE); // the old 60-second-old price is not reused
    }

    public function test_when_switched_off_it_fails_without_calling_anyone(): void
    {
        config(['goldprice.enabled' => false]);
        $this->fake();

        $this->assertFailsWith(GoldPriceException::NOT_CONFIGURED);
        Http::assertNothingSent();
    }

    public function test_a_provider_error_body_is_a_failure_not_a_price(): void
    {
        $this->fake($this->gold('{"error":"Symbol not found"}') + $this->fxOk());

        $this->assertFailsWith(GoldPriceException::INVALID_RESPONSE);
    }

    public function test_the_quote_must_be_gold_in_dollars(): void
    {
        foreach (['{"symbol":"XAG","currency":"USD","price":31.5}', '{"symbol":"XAU","currency":"EUR","price":3500}'] as $body) {
            Cache::flush();
            $this->fake($this->gold($body) + $this->fxOk());
            $this->assertFailsWith(GoldPriceException::INVALID_RESPONSE);
        }
    }

    public function test_rate_limit_and_server_errors_map_to_their_reasons(): void
    {
        $this->fake($this->gold('{}', 429) + $this->fxOk());
        $this->assertFailsWith(GoldPriceException::RATE_LIMITED);

        Cache::flush();
        $this->fake($this->gold('oops', 503) + $this->fxOk());
        $this->assertFailsWith(GoldPriceException::UNAVAILABLE);
    }

    public function test_a_network_failure_is_unavailable(): void
    {
        $this->fake(fn () => throw new ConnectionException('timeout'));

        $this->assertFailsWith(GoldPriceException::UNAVAILABLE);
    }

    public function test_a_failure_is_remembered_briefly_so_the_provider_is_not_hammered(): void
    {
        $this->fake($this->gold('{}', 429) + $this->fxOk());

        $this->assertFailsWith(GoldPriceException::RATE_LIMITED);
        $this->assertFailsWith(GoldPriceException::RATE_LIMITED);
        $this->assertSame(1, $this->sentTo('gold-api.com'));

        $this->travel(31)->seconds();
        $this->assertFailsWith(GoldPriceException::RATE_LIMITED);
        $this->assertSame(2, $this->sentTo('gold-api.com'));
    }

    public function test_unexpected_shapes_and_non_positive_values_are_invalid_not_prices(): void
    {
        $head = '"symbol":"XAU","currency":"USD",';
        foreach (['{'.$head.'"name":"Gold"}', '{'.$head.'"price":0}', '{'.$head.'"price":"0.0000"}', '{'.$head.'"price":-5}', '{'.$head.'"price":"abc"}', 'bukan json'] as $body) {
            Cache::flush();
            $this->fake($this->gold($body) + $this->fxOk());
            $this->assertFailsWith(GoldPriceException::INVALID_RESPONSE);
        }
    }

    public function test_a_wrong_or_zero_exchange_rate_is_invalid(): void
    {
        $badFx = [
            '{"date":"2026-11-03","base":"USD","quote":"EUR","rate":0.86}',
            '{"date":"2026-11-03","base":"USD","quote":"IDR","rate":0}',
            '{"date":"2026-11-03","base":"USD","quote":"IDR"}',
            '{"message":"not found"}',
        ];
        foreach ($badFx as $body) {
            Cache::flush();
            $this->fake($this->goldOk() + ['api.frankfurter.dev/*' => Http::response($body)]);
            $this->assertFailsWith(GoldPriceException::INVALID_RESPONSE);
        }
    }

    public function test_the_exception_carries_only_a_reason_never_an_upstream_body(): void
    {
        $this->fake($this->gold('{"error":"secret detail from upstream"}') + $this->fxOk());

        try {
            $this->quote();
            $this->fail('Expected a failure');
        } catch (GoldPriceException $e) {
            $this->assertStringNotContainsString('secret detail', $e->getMessage());
        }
    }
}
