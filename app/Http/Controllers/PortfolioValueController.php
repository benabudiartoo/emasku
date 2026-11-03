<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\GoldPrice\GoldPriceException;
use App\Services\GoldPrice\GoldPriceService;
use App\Services\PortfolioValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioValueController extends Controller
{
    /**
     * The value of the signed-in owner's gold at the latest price, and the comparison with the purchase price. When
     * the price cannot be had the answer is a 503 with a message and no figures: a failure is never a number.
     */
    public function show(Request $request, GoldPriceService $prices, PortfolioValue $value): JsonResponse
    {
        try {
            $quote = $prices->idrPerGram();
        } catch (GoldPriceException $e) {
            return response()->json([
                'error' => $e->reason,
                'message' => $e->reason === GoldPriceException::NOT_CONFIGURED
                    ? 'Harga otomatis dimatikan, jadi nilai rupiah tidak ditampilkan.'
                    : 'Harga tidak bisa diambil sekarang.',
            ], 503);
        }

        $items = Item::where('user_id', $request->user()->id)->with('units')->get();

        return response()->json([
            'price' => $quote->toArray(),
            ...$value->compute($items, $quote->pricePerGram),
        ]);
    }
}
