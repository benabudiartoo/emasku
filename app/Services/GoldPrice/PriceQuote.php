<?php

namespace App\Services\GoldPrice;

/** A gold price in rupiah per gram, with where and when it came from. Decimals are strings. */
final class PriceQuote
{
    public function __construct(
        public readonly string $pricePerGram,
        public readonly string $usdPerGram,
        public readonly string $fxRate,
        public readonly ?string $fxDate,
        public readonly string $source,
        public readonly string $fxSource,
        public readonly string $fetchedAt,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'price_per_gram' => $this->pricePerGram,
            'currency' => 'IDR',
            'source' => $this->source,
            'fx_source' => $this->fxSource,
            'fx_date' => $this->fxDate,
            'fetched_at' => $this->fetchedAt,
            'note' => 'Harga spot internasional yang dikonversi ke rupiah, bukan harga toko emas.',
        ];
    }
}
