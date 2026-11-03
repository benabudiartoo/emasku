<?php

namespace App\Services\Zakat;

/**
 * Everything one calculation depends on. The calculator reads nothing else.
 */
final class ZakatInput
{
    /**
     * @param  list<ZakatItem>  $items
     * @param  string  $valuationDate  Y-m-d
     * @param  string|null  $pricePerGram  rupiah per gram of pure gold, optional
     * @param  string|null  $manualHaulStart  Y-m-d, replaces the haul start derived from the items
     */
    public function __construct(
        public readonly string $madhhab,
        public readonly string $valuationDate,
        public readonly array $items,
        public readonly ?string $pricePerGram = null,
        public readonly ?string $manualHaulStart = null,
    ) {}
}
