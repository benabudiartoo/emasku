<?php

namespace App\Services\Zakat;

use App\Models\Item;
use App\Services\GoldCalculator;

/**
 * What the calculator needs to know about one item, frozen as plain values (no model, no database).
 */
final class ZakatItem
{
    /** @param  list<string>  $missing  fields that stop pure gold from being calculated */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $kind,
        public readonly string $ownership,
        public readonly ?string $purpose,
        public readonly ?string $pureGold,
        public readonly array $missing,
        public readonly ?string $date,
        public readonly string $dateCertainty,
    ) {}

    public static function fromItem(Item $item, GoldCalculator $calc): self
    {
        $pure = $calc->pureGold($item);

        // A known haul start for the item wins over owned-since.
        $useHaul = $item->haul_start !== null;

        return new self(
            id: (string) $item->id,
            name: (string) $item->name,
            kind: (string) $item->kind,
            ownership: (string) $item->ownership,
            purpose: $item->purpose,
            pureGold: $pure->value,
            missing: $pure->missing,
            date: ($useHaul ? $item->haul_start : $item->owned_since)?->format('Y-m-d'),
            dateCertainty: ($useHaul ? $item->haul_start_certainty : $item->owned_since_certainty) ?? 'unknown',
        );
    }
}
