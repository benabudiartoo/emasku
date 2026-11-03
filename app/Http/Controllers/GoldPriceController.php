<?php

namespace App\Http\Controllers;

use App\Services\GoldPrice\GoldPriceException;
use App\Services\GoldPrice\GoldPriceService;
use Illuminate\Http\JsonResponse;

class GoldPriceController extends Controller
{
    /**
     * The current gold price in rupiah per gram, or a 503 with a message and no figure. The response carries
     * only what the page shows; the API key and the upstream bodies never leave the server.
     */
    public function show(GoldPriceService $prices): JsonResponse
    {
        try {
            $quote = $prices->idrPerGram();
        } catch (GoldPriceException $e) {
            return response()->json([
                'error' => $e->reason,
                'message' => $e->reason === GoldPriceException::NOT_CONFIGURED
                    ? 'Harga otomatis belum dipasang. Isi harga manual.'
                    : 'Harga tidak bisa diambil sekarang. Isi manual.',
            ], 503);
        }

        return response()->json($quote->toArray());
    }
}
