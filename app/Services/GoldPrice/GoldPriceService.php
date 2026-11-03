<?php

namespace App\Services\GoldPrice;

use App\Services\GoldCalculator;
use Illuminate\Support\Facades\Cache;

/**
 * Rupiah per gram of gold = USD per gram x USD to IDR rate, with fixed-precision arithmetic.
 *
 * One cache is shared by every owner, so the provider is called at most once per cache lifetime however many people
 * ask. Gold is cached for 60 seconds at most and an expired value is never served again;
 * a failure is remembered only briefly so a failing provider is not hammered, and it never becomes a price.
 */
class GoldPriceService
{
    private const GOLD_KEY = 'goldprice:gold:usd-per-gram';

    private const FX_KEY = 'goldprice:fx:usd-idr';

    private const FAILURE_KEY = 'goldprice:failure';

    public function __construct(
        private GoldApiClient $gold = new GoldApiClient,
        private FrankfurterClient $fx = new FrankfurterClient,
    ) {}

    /** @throws GoldPriceException */
    public function idrPerGram(): PriceQuote
    {
        // Switched off: nothing to call; say so every time, without caching.
        if (! config('goldprice.enabled')) {
            throw new GoldPriceException(GoldPriceException::NOT_CONFIGURED);
        }

        if (is_string($failed = Cache::get(self::FAILURE_KEY))) {
            throw new GoldPriceException($failed);
        }

        try {
            $gold = Cache::remember(self::GOLD_KEY, (int) config('goldprice.gold.cache_seconds'), fn () => [
                'usd_per_gram' => $this->gold->usdPerGram(),
                'fetched_at' => now()->toIso8601String(),
            ]);
            $fx = Cache::remember(self::FX_KEY, (int) config('goldprice.fx.cache_seconds'), fn () => $this->fx->usdToIdr());
        } catch (GoldPriceException $e) {
            if (in_array($e->reason, [GoldPriceException::RATE_LIMITED, GoldPriceException::UNAVAILABLE], true)) {
                Cache::put(self::FAILURE_KEY, $e->reason, (int) config('goldprice.failure_cache_seconds'));
            }

            throw $e;
        }

        // Round half up to 2 decimals only at the end; refuse anything that is not a positive price.
        $idr = GoldCalculator::round(bcmul($gold['usd_per_gram'], $fx['rate'], GoldCalculator::SCALE), 2);
        if (bccomp($idr, '0', 2) <= 0) {
            throw new GoldPriceException(GoldPriceException::INVALID_RESPONSE);
        }

        return new PriceQuote(
            pricePerGram: $idr,
            usdPerGram: $gold['usd_per_gram'],
            fxRate: $fx['rate'],
            fxDate: $fx['date'],
            source: config('goldprice.attribution.name'),
            fxSource: 'Frankfurter',
            fetchedAt: $gold['fetched_at'],
        );
    }
}
