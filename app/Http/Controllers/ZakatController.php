<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ZakatPeriod;
use App\Services\GoldCalculator;
use App\Services\Zakat\ZakatCalculator;
use App\Services\Zakat\ZakatInput;
use App\Services\Zakat\ZakatItem;
use App\Services\Zakat\ZakatPresenter;
use App\Services\Zakat\ZakatResult;
use App\Services\Zakat\ZakatRuleSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ZakatController extends Controller
{
    public function index(Request $request): Response
    {
        $periods = ZakatPeriod::where('user_id', $request->user()->id)
            ->orderByDesc('valuation_date')->orderByDesc('created_at')->get();

        return Inertia::render('Zakat/Index', [
            'periods' => $periods->map(fn (ZakatPeriod $p) => ZakatPresenter::summary($p))->values(),
            'madhhabs' => ZakatPresenter::madhhabs(),
        ]);
    }

    /**
     * The form, and the preview when `date` is present. A preview is a GET with query parameters and writes
     * nothing: the result is computed on the server from the signed-in owner's own items.
     */
    public function hitung(Request $request): Response
    {
        $items = $this->ownerItems($request->user()->id);
        $hasJewelry = $items->contains('kind', 'jewelry');

        $inputs = [
            'date' => now()->toDateString(),
            'madhhab' => ZakatRuleSet::supported()[0] ?? '',
            'price' => '',
            'haul_start' => '',
            'jewelry_confirmed' => false,
        ];
        $result = null;
        $errors = [];

        if ($request->query->has('date')) {
            $query = $this->normalized($request->query());
            $inputs = [
                'date' => (string) ($query['date'] ?? ''),
                'madhhab' => (string) ($query['madhhab'] ?? $inputs['madhhab']),
                'price' => (string) ($query['price'] ?? ''),
                'haul_start' => (string) ($query['haul_start'] ?? ''),
                'jewelry_confirmed' => filter_var($query['jewelry_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];

            $validator = Validator::make($query, $this->rules($hasJewelry), $this->messages());
            if ($validator->fails()) {
                $errors = collect($validator->errors()->messages())->map(fn (array $m) => $m[0])->all();
            } else {
                $result = ZakatPresenter::fromResult($this->calculate($validator->validated(), $items));
            }
        }

        return Inertia::render('Zakat/Hitung', [
            'madhhabs' => ZakatPresenter::madhhabs(),
            'inputs' => $inputs,
            'has_jewelry' => $hasJewelry,
            'result' => $result,
            'form_errors' => $errors,
            // The button is offered only when a key is set. The key itself never goes to the page.
            'price_feed' => [
                'available' => (bool) config('goldprice.enabled'),
                'attribution' => config('goldprice.attribution'),
            ],
        ]);
    }

    /**
     * Save a frozen copy. Only the inputs are read: the result is always recomputed here, and the owner always
     * comes from the session, so a client cannot supply either.
     */
    public function store(Request $request): RedirectResponse
    {
        $items = $this->ownerItems($request->user()->id);
        $request->merge($this->normalized($request->all()));

        $data = $request->validate($this->rules($items->contains('kind', 'jewelry')), $this->messages());

        $period = ZakatPeriod::fromResult($this->calculate($data, $items), $request->user()->id);
        $period->save();

        return redirect('/zakat/periode/'.$period->id)
            ->with('toast', ['type' => 'success', 'message' => 'Perhitungan disimpan']);
    }

    public function show(Request $request, ZakatPeriod $period): Response
    {
        Gate::authorize('view', $period);

        $view = ZakatPresenter::fromPeriod($period);

        // Link a contributing item only while it still exists for this owner (it may have been deleted since).
        $existing = Item::where('user_id', $request->user()->id)
            ->whereIn('id', array_column($view['contributions'], 'item_id'))->pluck('id')->all();
        $view['contributions'] = array_map(fn (array $c) => [...$c, 'linkable' => in_array($c['item_id'], $existing, true)], $view['contributions']);

        return Inertia::render('Zakat/Periode', ['period' => $view]);
    }

    /** @return Collection<int, Item> */
    private function ownerItems(string $userId): Collection
    {
        return Item::where('user_id', $userId)->with('units')->get();
    }

    private function calculate(array $data, Collection $items): ZakatResult
    {
        $calc = new GoldCalculator;
        $zakatItems = $items->map(fn (Item $i) => ZakatItem::fromItem($i, $calc))->all();

        return (new ZakatCalculator)->calculate(new ZakatInput(
            madhhab: $data['madhhab'],
            valuationDate: $data['date'],
            items: $zakatItems,
            pricePerGram: isset($data['price']) ? (string) $data['price'] : null,
            manualHaulStart: $data['haul_start'] ?? null,
        ));
    }

    /** The price may be typed with an Indonesian decimal comma. */
    private function normalized(array $input): array
    {
        if (isset($input['price']) && is_string($input['price'])) {
            $input['price'] = str_replace(',', '.', trim($input['price']));
        }

        return $input;
    }

    private function rules(bool $hasJewelry): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1950-01-01', 'before_or_equal:today'],
            // Only madhhab with a complete rule sheet are accepted, so the list can grow by adding sheets.
            'madhhab' => ['required', Rule::in(ZakatRuleSet::supported())],
            'price' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
            'haul_start' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1950-01-01', 'before_or_equal:date'],
            'jewelry_confirmed' => $hasJewelry ? ['accepted'] : ['nullable'],
        ];
    }

    private function messages(): array
    {
        return [
            'date.required' => 'Isi tanggal penilaian.',
            'date.date_format' => 'Tanggal penilaian tidak valid.',
            'date.before_or_equal' => 'Tanggal penilaian tidak boleh di masa depan.',
            'madhhab.required' => 'Pilih mazhab.',
            'madhhab.in' => 'Mazhab itu belum tersedia.',
            'price.numeric' => 'Harga per gram harus berupa angka.',
            'price.gt' => 'Harga per gram harus lebih dari nol.',
            'haul_start.date_format' => 'Awal haul tidak valid.',
            'haul_start.before_or_equal' => 'Awal haul tidak boleh setelah tanggal penilaian.',
            'jewelry_confirmed.accepted' => 'Centang konfirmasi bahwa tujuan tiap perhiasan sudah Anda periksa.',
        ];
    }
}
