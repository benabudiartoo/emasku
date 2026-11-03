<?php

namespace App\Services\GoldPrice;

use RuntimeException;

/**
 * Why a price could not be given. Carries only a reason code: no URL, no key, no upstream body, so nothing
 * sensitive can reach a log or a response through it.
 */
class GoldPriceException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const RATE_LIMITED = 'rate_limited';

    public const UNAVAILABLE = 'unavailable';

    public const INVALID_RESPONSE = 'invalid_response';

    public function __construct(public readonly string $reason)
    {
        parent::__construct("Gold price unavailable: {$reason}");
    }
}
