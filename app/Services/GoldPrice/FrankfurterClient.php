<?php

namespace App\Services\GoldPrice;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * USD to IDR exchange rate from Frankfurter (no key). Read as text, like the gold price.
 */
class FrankfurterClient
{
    /**
     * @return array{rate: string, date: ?string}
     *
     * @throws GoldPriceException
     */
    public function usdToIdr(): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(4)
                ->timeout((int) config('goldprice.timeout_seconds', 8))
                ->get(rtrim(config('goldprice.fx.base_url'), '/').'/v2/rate/usd/idr');
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

        // Make sure it really is USD to IDR before trusting the number.
        $isUsdIdr = preg_match('/"base"\s*:\s*"USD"/', $body) === 1 && preg_match('/"quote"\s*:\s*"IDR"/', $body) === 1;
        if (! $isUsdIdr || preg_match('/"rate"\s*:\s*"?([0-9]+(?:\.[0-9]+)?)"?/', $body, $m) !== 1 || bccomp($m[1], '0', 10) <= 0) {
            throw new GoldPriceException(GoldPriceException::INVALID_RESPONSE);
        }

        $date = preg_match('/"date"\s*:\s*"(\d{4}-\d{2}-\d{2})"/', $body, $d) === 1 ? $d[1] : null;

        return ['rate' => $m[1], 'date' => $date];
    }
}
