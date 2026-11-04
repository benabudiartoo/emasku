<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\GoldCalculator;
use App\Services\GoldPrice\GoldPriceException;
use App\Services\GoldPrice\GoldPriceService;
use App\Services\ResaleEstimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ResaleEstimateController extends Controller
{
    /**
     * The resale estimate of one of the owner's items. The part that needs no market price (the discounted price) is
     * always given; when the gold price cannot be had, the market part is left out and says why, instead of failing.
     */
    public function show(Item $item, GoldCalculator $calc, GoldPriceService $prices, ResaleEstimate $resale): JsonResponse
    {
        Gate::authorize('view', $item);
        $item->loadMissing('units');

        $quote = null;
        $priceError = null;
        try {
            $quote = $prices->idrPerGram();
        } catch (GoldPriceException $e) {
            $priceError = $e->reason === GoldPriceException::NOT_CONFIGURED
                ? 'Harga otomatis dimatikan, jadi tidak ada pembanding harga pasar.'
                : 'Harga pasar tidak bisa diambil sekarang, jadi tidak ada pembanding.';
        }

        return response()->json([
            'estimate' => $resale->estimate($item, $calc->netWeight($item), $calc->pureGold($item), $quote?->pricePerGram),
            'price' => $quote?->toArray(),
            'price_message' => $priceError,
        ]);
    }
}
