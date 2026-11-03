<?php

namespace App\Services;

use App\Models\Item;

/**
 * The gram figures behind the summary charts. Only the owner's own items count. The per kind and per location charts
 * are pure gold, so they need complete data (purity included); the growth chart is the total weight (net metal weight),
 * which does not need the purity. Weights are summed from unrounded values and rounded once (4 decimals) for display.
 * Nothing is ever turned into zero: a kind or a location without such an item simply has no row.
 */
class SummaryCharts
{
    /** Locations shown on their own; the rest are grouped as "Lainnya". */
    public const TOP_LOCATIONS = 5;

    public function __construct(private GoldCalculator $calc) {}

    /**
     * @param  iterable<Item>  $items  with `units` and `location` loaded
     * @return array{
     *     by_kind: list<array{label: string, pure_gold_g: string}>,
     *     by_location: list<array{label: string, pure_gold_g: string}>,
     *     growth: array{points: list<array{date: string, cumulative_g: string}>, undated_count: int, approximate_count: int}
     * }
     */
    public function build(iterable $items): array
    {
        $complete = [];
        $weighed = [];
        foreach ($items as $item) {
            if ($item->ownership !== 'own') {
                continue;
            }
            $pure = $this->calc->pureGold($item);
            if ($pure->isComplete()) {
                $complete[] = ['item' => $item, 'pure' => $pure->value];
            }
            // Total weight does not need the purity, so an item with an unknown purity still counts here.
            $net = $this->calc->netWeight($item);
            if ($net->isComplete()) {
                $weighed[] = ['item' => $item, 'weight' => $net->value];
            }
        }

        return [
            'by_kind' => $this->byKind($complete),
            'by_location' => $this->byLocation($complete),
            'growth' => $this->growth($weighed),
        ];
    }

    /** @param  list<array{item: Item, pure: string}>  $complete */
    private function byKind(array $complete): array
    {
        $sums = [];
        foreach ($complete as ['item' => $item, 'pure' => $pure]) {
            $sums[$item->kind] = bcadd($sums[$item->kind] ?? '0', $pure, GoldCalculator::SCALE);
        }

        $rows = [];
        foreach (['bar' => 'Batangan', 'jewelry' => 'Perhiasan'] as $kind => $label) {
            if (isset($sums[$kind])) {
                $rows[] = ['label' => $label, 'pure_gold_g' => GoldCalculator::round($sums[$kind], 4)];
            }
        }

        return $rows;
    }

    /** @param  list<array{item: Item, pure: string}>  $complete */
    private function byLocation(array $complete): array
    {
        $sums = [];
        foreach ($complete as ['item' => $item, 'pure' => $pure]) {
            $name = $item->location?->name ?? 'Tanpa lokasi';
            $sums[$name] = bcadd($sums[$name] ?? '0', $pure, GoldCalculator::SCALE);
        }

        // Largest first; ties by name so the order is stable.
        uksort($sums, fn (string $a, string $b) => bccomp($sums[$b], $sums[$a], GoldCalculator::SCALE) ?: strcmp($a, $b));

        $top = array_slice($sums, 0, self::TOP_LOCATIONS, true);
        $rest = array_slice($sums, self::TOP_LOCATIONS, null, true);
        if ($rest !== []) {
            $top['Lainnya'] = array_reduce($rest, fn (string $sum, string $v) => bcadd($sum, $v, GoldCalculator::SCALE), '0');
        }

        $rows = [];
        foreach ($top as $label => $sum) {
            $rows[] = ['label' => (string) $label, 'pure_gold_g' => GoldCalculator::round($sum, 4)];
        }

        return $rows;
    }

    /**
     * Cumulative TOTAL weight (net metal weight, whatever the purity) by ownership date, not only the pure gold.
     * The key is still `cumulative_g`; it now holds the net weight.
     *
     * @param  list<array{item: Item, weight: string}>  $weighed
     */
    private function growth(array $weighed): array
    {
        $byDate = [];
        $undated = 0;
        $approximate = 0;

        foreach ($weighed as ['item' => $item, 'weight' => $pure]) {
            $date = $item->owned_since?->format('Y-m-d');
            if ($date === null) {
                $undated++;

                continue;
            }
            if ($item->owned_since_certainty === 'approximate') {
                $approximate++;
            }
            $byDate[$date] = bcadd($byDate[$date] ?? '0', $pure, GoldCalculator::SCALE);
        }

        ksort($byDate);

        $points = [];
        $running = '0';
        foreach ($byDate as $date => $added) {
            $running = bcadd($running, $added, GoldCalculator::SCALE);
            $points[] = ['date' => (string) $date, 'cumulative_g' => GoldCalculator::round($running, 4)];
        }

        return ['points' => $points, 'undated_count' => $undated, 'approximate_count' => $approximate];
    }
}
