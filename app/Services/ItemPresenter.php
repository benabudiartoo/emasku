<?php

namespace App\Services;

use App\Models\Item;

/**
 * Turns an Item into the array the pages need. Calculated weights are rounded here, for display only.
 */
class ItemPresenter
{
    /**
     * The owner's items as presented arrays, newest first, narrowed by the collection filters (jenis, toko, data).
     * The collection page and the collection export both use this, so they always list the same items.
     *
     * @param  array{jenis?: ?string, data?: ?string, toko?: ?string}  $filters
     * @return \Illuminate\Support\Collection<int, array>
     */
    public static function filtered(string $userId, array $filters, GoldCalculator $calc): \Illuminate\Support\Collection
    {
        return Item::where('user_id', $userId)->with('units')->latest()->get()
            ->map(fn (Item $i) => self::present($i, $calc))
            ->filter(fn (array $i) => match ($filters['jenis'] ?? null) {
                'bar', 'jewelry' => $i['kind'] === $filters['jenis'],
                default => true,
            })
            ->filter(fn (array $i) => ($filters['toko'] ?? null) ? ($i['purchase_store'] ?? null) === $filters['toko'] : true)
            ->filter(fn (array $i) => match ($filters['data'] ?? null) {
                'lengkap' => $i['status'] === 'complete',
                'kurang' => $i['status'] === 'needs_data',
                default => true,
            })
            ->values();
    }

    /**
     * Purchase price divided by the net weight, rounded once to 2 decimals. Not stored. Null (never zero) when the
     * price or the weight is unknown or the weight is not positive.
     */
    public static function pricePerGram(Item $item, GoldResult $net): ?string
    {
        if ($item->acquisition_cost === null || ! $net->isComplete() || bccomp($net->value, '0', GoldCalculator::SCALE) <= 0) {
            return null;
        }

        return GoldCalculator::round(bcdiv((string) $item->acquisition_cost, $net->value, GoldCalculator::SCALE), 2);
    }

    public static function present(Item $item, GoldCalculator $calc): array
    {
        $net = $calc->netWeight($item);
        $pure = $calc->pureGold($item);
        $round = fn (?string $v) => $v === null ? null : GoldCalculator::round($v, 4);

        $item->loadMissing('units');

        return [
            ...$item->only([
                'id', 'kind', 'name', 'brand', 'jewelry_type', 'package_condition', 'ownership', 'held_for',
                'unit_weight_g', 'gross_weight_g', 'stone_state', 'stone_weight_g', 'net_weight_g',
                'purity_source', 'purity_input', 'purpose', 'acquisition_cost',
                'purchase_store', 'resale_discount_percent',
                'owned_since_certainty', 'haul_start_certainty', 'haul_start_note',
                'notes',
            ]),
            'owned_since' => $item->owned_since?->format('Y-m-d'),
            'haul_start' => $item->haul_start?->format('Y-m-d'),
            'created_at' => $item->created_at?->format('Y-m-d'),
            'units' => $item->units->map(fn ($u) => ['id' => $u->id, 'serial_number' => $u->serial_number, 'quantity' => $u->quantity])->values(),
            'pieces' => (int) $item->units->sum('quantity'),
            'purity_karat' => $item->purity_fraction === null ? null : GoldCalculator::round(bcmul((string) $item->purity_fraction, '24', GoldCalculator::SCALE), 4),
            'purity_estimate' => GoldCalculator::isPurityEstimate($item),
            'price_per_gram' => self::pricePerGram($item, $net),
            'discounted_price' => ResaleEstimate::discounted($item, $net),
            'net_g' => $round($net->value),
            'pure_gold_g' => $round($pure->value),
            'status' => $pure->status,
            'missing' => $pure->missing,
        ];
    }
}
