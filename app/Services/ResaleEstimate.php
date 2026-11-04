<?php

namespace App\Services;

use App\Models\Item;

/**
 * What an item might fetch when sold.
 *
 * discounted = price per gram x (1 - discount) x net weight. Since price per gram is cost / net weight this is simply
 * cost x (1 - discount). market = the item's pure gold grams x the latest gold price per gram. The estimate is the
 * LARGER of the two, so a market price above the discounted price wins. If the market price is not known (the price
 * could not be fetched, or the item has no purity) only the discounted price is given, and it says so: a missing
 * market price is never zero and never assumed equal to anything.
 *
 * An item without a purchase price, a net weight, or a discount has no estimate (null with the reasons), never zero.
 * Everything is a decimal string and is rounded once, to 2 decimals, at the end.
 */
class ResaleEstimate
{
    public const BASIS_MARKET = 'market';

    public const BASIS_DISCOUNTED = 'discounted';

    /** Only the discounted price is known; there is no market price to compare with. */
    public const BASIS_DISCOUNTED_ONLY = 'discounted_only';

    /** The price after the discount (no market comparison), rounded, or null when it cannot be worked out. */
    public static function discounted(Item $item, GoldResult $net): ?string
    {
        $exact = self::discountedExact($item, $net);

        return $exact === null ? null : GoldCalculator::round($exact, 2);
    }

    /** @return list<string> what is missing for an estimate: acquisition_cost, resale_discount_percent, net_weight */
    public static function missing(Item $item, GoldResult $net): array
    {
        $missing = [];
        if ($item->acquisition_cost === null) {
            $missing[] = 'acquisition_cost';
        }
        if ($item->resale_discount_percent === null) {
            $missing[] = 'resale_discount_percent';
        }
        if (! $net->isComplete() || bccomp($net->value, '0', GoldCalculator::SCALE) <= 0) {
            $missing[] = 'net_weight';
        }

        return $missing;
    }

    /**
     * @param  ?string  $pricePerGram  rupiah per gram of pure gold, or null when the price is not available
     * @return array{
     *     estimable: bool, missing: list<string>, discounted: ?string, market: ?string, estimate: ?string,
     *     basis: ?string, market_reason: ?string
     * }
     */
    public function estimate(Item $item, GoldResult $net, GoldResult $pure, ?string $pricePerGram): array
    {
        return array_diff_key($this->compute($item, $net, $pure, $pricePerGram), ['best_exact' => 1]);
    }

    /** estimate() plus the unrounded best value (for summing); that value never leaves this class. */
    private function compute(Item $item, GoldResult $net, GoldResult $pure, ?string $pricePerGram): array
    {
        $missing = self::missing($item, $net);
        if ($missing !== []) {
            return ['estimable' => false, 'missing' => $missing, 'discounted' => null, 'market' => null, 'estimate' => null, 'basis' => null, 'market_reason' => null, 'best_exact' => null];
        }

        $discounted = self::discountedExact($item, $net);
        $market = null;
        $marketReason = null;
        if ($pricePerGram === null) {
            $marketReason = 'price_unavailable';
        } elseif (! $pure->isComplete()) {
            $marketReason = 'purity_unknown';
        } else {
            $market = bcmul($pure->value, $pricePerGram, GoldCalculator::SCALE);
        }

        if ($market === null) {
            $basis = self::BASIS_DISCOUNTED_ONLY;
            $best = $discounted;
        } elseif (bccomp($market, $discounted, GoldCalculator::SCALE) > 0) {
            $basis = self::BASIS_MARKET;
            $best = $market;
        } else {
            $basis = self::BASIS_DISCOUNTED; // equal counts as the discounted price
            $best = $discounted;
        }

        return [
            'estimable' => true,
            'missing' => [],
            'discounted' => GoldCalculator::round($discounted, 2),
            'market' => $market === null ? null : GoldCalculator::round($market, 2),
            'estimate' => GoldCalculator::round($best, 2),
            'basis' => $basis,
            'market_reason' => $marketReason,
            'best_exact' => $best,
        ];
    }

    /**
     * Totals over the owner's own items: the estimated resale total, the purchase price of the SAME items, and counts.
     * Items that cannot be estimated are counted apart, never added as zero.
     *
     * @param  iterable<Item>  $items  with `units` loaded
     * @return array{estimated_total: string, cost: string, difference: string, difference_percent: ?string, items_estimated: int, items_using_market: int, items_not_estimable: int}
     */
    public function summarize(iterable $items, GoldCalculator $calc, ?string $pricePerGram): array
    {
        $total = '0';
        $cost = '0';
        $estimated = 0;
        $usingMarket = 0;
        $notEstimable = 0;

        foreach ($items as $item) {
            if ($item->ownership !== 'own') {
                continue;
            }

            $r = $this->compute($item, $calc->netWeight($item), $calc->pureGold($item), $pricePerGram);
            if (! $r['estimable']) {
                // An item with no discount set is simply not part of this view; one that has a discount but lacks the rest is counted.
                if ($item->resale_discount_percent !== null) {
                    $notEstimable++;
                }

                continue;
            }

            $estimated++;
            $total = bcadd($total, $r['best_exact'], GoldCalculator::SCALE);
            $cost = bcadd($cost, (string) $item->acquisition_cost, GoldCalculator::SCALE);
            if ($r['basis'] === self::BASIS_MARKET) {
                $usingMarket++;
            }
        }

        $difference = bcsub($total, $cost, GoldCalculator::SCALE);

        return [
            'estimated_total' => GoldCalculator::round($total, 2),
            'cost' => GoldCalculator::round($cost, 2),
            'difference' => GoldCalculator::round($difference, 2),
            'difference_percent' => bccomp($cost, '0', GoldCalculator::SCALE) > 0
                ? GoldCalculator::round(bcdiv(bcmul($difference, '100', GoldCalculator::SCALE), $cost, GoldCalculator::SCALE), 1)
                : null,
            'items_estimated' => $estimated,
            'items_using_market' => $usingMarket,
            'items_not_estimable' => $notEstimable,
        ];
    }

    private static function discountedExact(Item $item, GoldResult $net): ?string
    {
        if (self::missing($item, $net) !== []) {
            return null;
        }

        $factor = bcsub('1', bcdiv((string) $item->resale_discount_percent, '100', GoldCalculator::SCALE), GoldCalculator::SCALE);

        return bcmul((string) $item->acquisition_cost, $factor, GoldCalculator::SCALE);
    }
}
