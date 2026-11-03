<?php

namespace App\Services;

use App\Models\Item;

/**
 * What the owner's gold is worth today in rupiah, and how that compares with what they paid.
 *
 * Value = pure gold grams x rupiah per gram, with fixed-precision arithmetic and one rounding at the end. Only the
 * owner's own items with complete data count. The comparison with the purchase price uses only the items whose
 * purchase price is known, and sets their cost against the value of those same items, so an item with an unknown cost
 * can never make the result look like a gain. With no such item there is no comparison (null), never a zero.
 */
class PortfolioValue
{
    public function __construct(private GoldCalculator $calc) {}

    /**
     * @param  iterable<Item>  $items  with `units` loaded
     * @param  string  $pricePerGram  rupiah per gram, a positive decimal string
     * @return array{
     *     total_value: string,
     *     comparison: ?array{cost: string, value: string, difference: string, difference_percent: ?string, items_with_cost: int, items_without_cost: int}
     * }
     */
    public function compute(iterable $items, string $pricePerGram): array
    {
        $total = '0';
        $cost = '0';
        $valueOfCosted = '0';
        $withCost = 0;
        $withoutCost = 0;

        foreach ($items as $item) {
            if ($item->ownership !== 'own') {
                continue;
            }
            $pure = $this->calc->pureGold($item);
            if (! $pure->isComplete()) {
                continue;
            }

            $value = bcmul($pure->value, $pricePerGram, GoldCalculator::SCALE);
            $total = bcadd($total, $value, GoldCalculator::SCALE);

            if ($item->acquisition_cost === null) {
                $withoutCost++;

                continue;
            }
            $withCost++;
            $cost = bcadd($cost, (string) $item->acquisition_cost, GoldCalculator::SCALE);
            $valueOfCosted = bcadd($valueOfCosted, $value, GoldCalculator::SCALE);
        }

        $comparison = null;
        if ($withCost > 0) {
            $difference = bcsub($valueOfCosted, $cost, GoldCalculator::SCALE);
            $comparison = [
                'cost' => GoldCalculator::round($cost, 2),
                'value' => GoldCalculator::round($valueOfCosted, 2),
                'difference' => GoldCalculator::round($difference, 2),
                // A purchase price of zero has no meaningful percentage.
                'difference_percent' => bccomp($cost, '0', GoldCalculator::SCALE) > 0
                    ? GoldCalculator::round(bcdiv(bcmul($difference, '100', GoldCalculator::SCALE), $cost, GoldCalculator::SCALE), 1)
                    : null,
                'items_with_cost' => $withCost,
                'items_without_cost' => $withoutCost,
            ];
        }

        return ['total_value' => GoldCalculator::round($total, 2), 'comparison' => $comparison];
    }
}
