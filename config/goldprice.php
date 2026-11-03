<?php

/**
 * Gold price feed for the zakat form: gold-api.com gives gold in USD per troy ounce (no key), Frankfurter gives
 * USD to IDR, and the service converts to rupiah per gram. goldprice.org is not used (its terms forbid automated
 * access) and neither is GoldPriceZ (it needs a registered key).
 *
 * gold-api.com bans clients that send several requests per second, so the gold price is cached for 60 seconds and
 * shared by every owner. The lifetimes are deliberately not environment settings.
 */
return [
    // Set GOLDPRICE_ENABLED=false to hide the "Ambil harga terbaru" button and stop all outgoing calls.
    'enabled' => env('GOLDPRICE_ENABLED', true),

    'gold' => [
        'base_url' => 'https://api.gold-api.com',
        'cache_seconds' => 60,
    ],

    'fx' => [
        'base_url' => 'https://api.frankfurter.dev',
        'cache_seconds' => 6 * 3600, // daily rates; Frankfurter encourages caching
    ],

    // After a rate-limit or provider failure, remember the failure briefly instead of hitting the provider again.
    'failure_cache_seconds' => 30,

    'timeout_seconds' => 8,

    // Lookups per owner per minute (the cache is shared, so this only guards the endpoint).
    'throttle_per_minute' => 6,

    // Not required by the provider; shown so the owner can see where the number came from.
    'attribution' => [
        'name' => 'Gold-API.com',
        'url' => 'https://gold-api.com',
    ],
];
