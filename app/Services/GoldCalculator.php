<?php

namespace App\Services;

use App\Models\Item;
use InvalidArgumentException;

/**
 * Net metal weight and pure gold, with bcmath strings only (no floats).
 * Weight has 4 decimals and purity 6, so products are exact at scale 10; rounding is for display only.
 */
class GoldCalculator
{
    public const SCALE = 10;

    public const PURITY_SCALE = 6;

    /**
     * Convert what the owner typed into a fraction.
     * permil: 750 -> 0.75, 999.9 -> 0.9999; percent: 75 -> 0.75; karat: 18 -> 18/24 (an estimate).
     */
    public static function purityFraction(string $source, string $input): string
    {
        [$divisor, $min, $max] = match ($source) {
            'permil' => ['1000', '0', '1000'],
            'percent' => ['100', '0', '100'],
            'karat' => ['24', '0', '24'],
            default => throw new InvalidArgumentException("Unknown purity source: {$source}"),
        };

        if (! is_numeric($input) || bccomp($input, $min, 4) <= 0 || bccomp($input, $max, 4) > 0) {
            throw new InvalidArgumentException("Purity {$input} is outside 0-{$max} for {$source}");
        }

        return self::round(bcdiv($input, $divisor, self::SCALE), self::PURITY_SCALE);
    }

    public static function isPurityEstimate(Item $item): bool
    {
        return $item->purity_source === 'karat';
    }

    /** Net metal weight in grams for the whole item (bars: per piece x pieces). */
    public function netWeight(Item $item): GoldResult
    {
        return $item->kind === 'bar' ? $this->barNet($item) : $this->jewelryNet($item);
    }

    public function pureGold(Item $item): GoldResult
    {
        $net = $this->netWeight($item);
        $missing = $net->missing;

        if ($item->purity_fraction === null) {
            $missing[] = 'purity_fraction';
        }

        if ($missing !== []) {
            return GoldResult::needsData($missing);
        }

        return GoldResult::complete(bcmul($net->value, (string) $item->purity_fraction, self::SCALE));
    }

    /**
     * Total net metal weight of the owner's own items, whatever their purity (so it is more than the pure gold).
     * An item whose weight cannot be worked out is counted apart, never added as zero; held-for-others items are skipped.
     *
     * @param  iterable<Item>  $items
     * @return array{net: string, incomplete: int}
     */
    public function totalNet(iterable $items): array
    {
        $total = '0';
        $incomplete = 0;

        foreach ($items as $item) {
            if ($item->ownership !== 'own') {
                continue;
            }

            $net = $this->netWeight($item);
            if ($net->isComplete()) {
                $total = bcadd($total, $net->value, self::SCALE);
            } else {
                $incomplete++;
            }
        }

        return ['net' => $total, 'incomplete' => $incomplete];
    }

    /**
     * Totals over the owner's items. Held-for-others items are excluded from everything,
     * and items with missing data are counted apart instead of being added as zero.
     *
     * @param  iterable<Item>  $items
     * @return array{pure_gold: string, complete: int, incomplete: int, counted: int, incomplete_items: list<Item>}
     */
    public function summarize(iterable $items): array
    {
        $total = '0';
        $complete = 0;
        $incomplete = [];

        foreach ($items as $item) {
            if ($item->ownership !== 'own') {
                continue;
            }

            $pure = $this->pureGold($item);
            if ($pure->isComplete()) {
                $total = bcadd($total, $pure->value, self::SCALE);
                $complete++;
            } else {
                $incomplete[] = $item;
            }
        }

        return [
            'pure_gold' => $total,
            'complete' => $complete,
            'incomplete' => count($incomplete),
            'counted' => $complete + count($incomplete),
            'incomplete_items' => $incomplete,
        ];
    }

    /** Round half up to $decimals for display. Never use before comparing or summing. */
    public static function round(string $value, int $decimals): string
    {
        $half = '0.'.str_repeat('0', $decimals).'5';

        return bccomp($value, '0', self::SCALE) >= 0
            ? bcadd($value, $half, $decimals)
            : bcsub($value, $half, $decimals);
    }

    private function barNet(Item $item): GoldResult
    {
        $missing = [];

        if ($item->unit_weight_g === null) {
            $missing[] = 'unit_weight_g';
        }

        $pieces = (int) $item->units->sum('quantity');
        if ($pieces < 1) {
            $missing[] = 'quantity';
        }

        if ($missing !== []) {
            return GoldResult::needsData($missing);
        }

        return GoldResult::complete(bcmul((string) $item->unit_weight_g, (string) $pieces, self::SCALE));
    }

    private function jewelryNet(Item $item): GoldResult
    {
        // A weighed net weight wins over gross minus stone.
        if ($item->net_weight_g !== null) {
            return GoldResult::complete(bcadd((string) $item->net_weight_g, '0', self::SCALE));
        }

        $missing = [];
        if ($item->gross_weight_g === null) {
            $missing[] = 'gross_weight_g';
        }

        $stone = match ($item->stone_state) {
            'none' => '0',
            'known' => $item->stone_weight_g === null ? null : (string) $item->stone_weight_g,
            default => null, // 'unknown' or never stated: not the same as "no stone"
        };
        if ($stone === null) {
            $missing[] = 'stone_weight_g';
        }

        if ($missing !== []) {
            return GoldResult::needsData($missing);
        }

        $net = bcsub((string) $item->gross_weight_g, $stone, self::SCALE);

        // Stone heavier than the whole piece is inconsistent input: ask for a fix instead of a negative weight.
        return bccomp($net, '0', self::SCALE) < 0
            ? GoldResult::needsData(['stone_weight_g'])
            : GoldResult::complete($net);
    }
}
