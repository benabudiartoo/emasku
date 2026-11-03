<?php

namespace App\Services\GoldPrice;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Gold spot price from gold-api.com (no key). It quotes USD per troy ounce; this converts to USD per gram. The number
 * is read from the raw body as text, never through a float, and anything that is not a positive number is an error
 * rather than a price.
 */
class GoldApiClient
{
    /** Grams in one troy ounce. */
    public const GRAMS_PER_TROY_OUNCE = '31.1034768';

    /** @throws GoldPriceException */
    public function usdPerGram(): string
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(4)
                ->timeout((int) config('goldprice.timeout_seconds', 8))
                ->get(rtrim(config('goldprice.gold.base_url'), '/').'/price/XAU');
        } catch (ConnectionException) {
            throw new GoldPriceException(GoldPriceException::UNAVAILABLE);
        }

        if ($response->status() === 429) {
            throw new GoldPriceException(GoldPriceException::RATE_LIMITED);
        }
        if ($response->failed()) {
            throw new GoldPriceException(GoldPriceException::UNAVAILABLE);
        }

        $body = $response->body();

        // The quote must really be gold priced in dollars.
        if (preg_match('/"symbol"\s*:\s*"XAU"/', $body) !== 1 || preg_match('/"currency"\s*:\s*"USD"/', $body) !== 1) {
            throw new GoldPriceException(GoldPriceException::INVALID_RESPONSE);
        }

        if (preg_match('/"price"\s*:\s*"?([0-9]+(?:\.[0-9]+)?)"?/', $body, $m) !== 1 || bccomp($m[1], '0', 10) <= 0) {
            throw new GoldPriceException(GoldPriceException::INVALID_RESPONSE);
        }

        return bcdiv($m[1], self::GRAMS_PER_TROY_OUNCE, 10);
    }
}
