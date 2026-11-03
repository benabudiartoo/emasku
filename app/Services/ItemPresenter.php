<?php

namespace App\Services;

use App\Models\Item;

/**
 * Turns an Item into the array the pages need. Calculated weights are rounded here, for display only.
 */
class ItemPresenter
{
    public static function present(Item $item, GoldCalculator $calc): array
    {
        $net = $calc->netWeight($item);
        $pure = $calc->pureGold($item);
        $round = fn (?string $v) => $v === null ? null : GoldCalculator::round($v, 4);

        $item->loadMissing('units', 'location');

        return [
            ...$item->only([
                'id', 'kind', 'name', 'brand', 'jewelry_type', 'package_condition', 'ownership', 'held_for',
                'unit_weight_g', 'gross_weight_g', 'stone_state', 'stone_weight_g', 'net_weight_g',
                'purity_source', 'purity_input', 'purpose', 'acquisition_method', 'acquisition_cost',
                'owned_since_certainty', 'owned_since_note', 'haul_start_certainty', 'haul_start_note',
                'notes', 'location_id',
            ]),
            'owned_since' => $item->owned_since?->format('Y-m-d'),
            'haul_start' => $item->haul_start?->format('Y-m-d'),
            'created_at' => $item->created_at?->format('Y-m-d'),
            'location' => $item->location ? ['id' => $item->location->id, 'name' => $item->location->name] : null,
            'units' => $item->units->map(fn ($u) => ['id' => $u->id, 'serial_number' => $u->serial_number, 'quantity' => $u->quantity])->values(),
            'pieces' => (int) $item->units->sum('quantity'),
            'purity_estimate' => GoldCalculator::isPurityEstimate($item),
            'net_g' => $round($net->value),
            'pure_gold_g' => $round($pure->value),
            'status' => $pure->status,
            'missing' => $pure->missing,
        ];
    }
}
