<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ZakatPeriod;
use App\Services\GoldCalculator;
use App\Services\ItemPresenter;
use App\Services\SummaryCharts;
use App\Services\Zakat\ZakatPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SummaryController extends Controller
{
    public function __construct(private GoldCalculator $calc) {}

    public function index(Request $request): Response
    {
        $items = Item::where('user_id', $request->user()->id)->with('units')->get();
        $summary = $this->calc->summarize($items);
        $weight = $this->calc->totalNet($items);

        return Inertia::render('Ringkasan/Index', [
            'net_weight_g' => GoldCalculator::round($weight['net'], 4),
            'net_incomplete' => $weight['incomplete'],
            'charts' => app(SummaryCharts::class)->build($items),
            'has_any' => $items->isNotEmpty(),
            'active_count' => $summary['counted'],
            'bars' => $items->where('ownership', 'own')->where('kind', 'bar')->count(),
            'jewelry' => $items->where('ownership', 'own')->where('kind', 'jewelry')->count(),
            'pure_gold_g' => GoldCalculator::round($summary['pure_gold'], 4),
            'complete' => $summary['complete'],
            'incomplete' => array_map(
                fn (Item $i) => ['id' => $i->id, 'name' => $i->name, 'missing' => $this->calc->pureGold($i)->missing],
                $summary['incomplete_items'],
            ),
            'today' => now()->toDateString(),
            'zakat' => $this->latestZakat($request->user()->id),
        ]);
    }

    /** The latest saved zakat period of this owner, for the "Status zakat" card; null before the first one. */
    private function latestZakat(string $userId): ?array
    {
        $latest = ZakatPeriod::where('user_id', $userId)->orderByDesc('valuation_date')->orderByDesc('created_at')->first();

        return $latest ? ZakatPresenter::summary($latest) : null;
    }

    public function detail(Request $request, string $metric): Response
    {
        abort_unless(in_array($metric, ['barang', 'emas-murni'], true), 404);

        $owned = Item::where('user_id', $request->user()->id)->where('ownership', 'own')->with('units')->orderBy('name')->get();
        $summary = $this->calc->summarize($owned);

        $present = fn ($items) => collect($items)->map(fn (Item $i) => ItemPresenter::present($i, $this->calc))->values();
        $complete = $owned->filter(fn (Item $i) => $this->calc->pureGold($i)->isComplete());

        // Split by kind from the unrounded values, so the parts add up to the total.
        $byKind = fn (string $kind) => GoldCalculator::round(
            $complete->where('kind', $kind)->reduce(fn (string $sum, Item $i) => bcadd($sum, $this->calc->pureGold($i)->value, GoldCalculator::SCALE), '0'),
            4,
        );

        return Inertia::render('Ringkasan/Rincian', [
            'metric' => $metric,
            'total_g' => GoldCalculator::round($summary['pure_gold'], 4),
            'bars_g' => $byKind('bar'),
            'jewelry_g' => $byKind('jewelry'),
            'items' => $present($metric === 'barang' ? $owned : $complete),
            'incomplete' => $present($summary['incomplete_items']),
            'today' => now()->toDateString(),
        ]);
    }
}
