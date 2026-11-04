<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Services\GoldCalculator;
use App\Services\ItemPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    /** Numeric inputs may be typed with an Indonesian decimal comma. */
    private const DECIMAL_FIELDS = ['unit_weight_g', 'gross_weight_g', 'stone_weight_g', 'net_weight_g', 'purity_input', 'acquisition_cost', 'resale_discount_percent'];

    /** Brands offered for gold bars. */
    public const BRANDS = ['Antam', 'Galeri 24', 'Lotus Archi', 'UBS', 'Hartadinata', 'Lainnya'];

    public function __construct(private GoldCalculator $calc) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $filters = $request->only(['jenis', 'data', 'toko']);

        $items = ItemPresenter::filtered($userId, $filters, $this->calc);

        return Inertia::render('Koleksi/Index', [
            'items' => $items,
            'filters' => $filters,
            'stores' => Item::where('user_id', $userId)->whereNotNull('purchase_store')->distinct()->orderBy('purchase_store')->pluck('purchase_store')->values(),
            'has_any' => Item::where('user_id', $userId)->exists(),
        ]);
    }

    public function create(Request $request, string $kind): Response
    {
        abort_unless(in_array($kind, ['batangan', 'perhiasan'], true), 404);

        return Inertia::render('Koleksi/Baru', [
            'kind' => $kind === 'batangan' ? 'bar' : 'jewelry',
            'old' => false,
        ]);
    }

    public function createOld(Request $request): Response
    {
        return Inertia::render('Koleksi/Baru', [
            'kind' => 'bar',
            'old' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $kind = $request->input('kind');
        abort_unless(in_array($kind, ['bar', 'jewelry'], true), 422);
        $old = $request->boolean('old');

        $data = $this->validated($request, $kind, $old);

        $item = DB::transaction(function () use ($request, $data, $kind) {
            $item = new Item($this->itemAttributes($data, $kind));
            $item->user_id = $request->user()->id; // guarded: always from the session
            $item->save();
            $this->syncUnits($item, $data, $kind);
            if ($request->hasFile('photo')) {
                $item->attachPhoto($request->file('photo'), $request->user()->id);
            }

            return $item;
        });

        if ($old && $request->input('then') === 'another') {
            return redirect('/koleksi/lama')->with('toast', ['type' => 'success', 'message' => 'Barang disimpan']);
        }
        if ($old) {
            return redirect('/koleksi')->with('toast', ['type' => 'success', 'message' => 'Koleksi lama dicatat']);
        }

        return redirect('/koleksi/'.$item->id)->with('toast', [
            'type' => 'success',
            'message' => $kind === 'bar' ? 'Emas batangan disimpan' : 'Perhiasan disimpan',
        ]);
    }

    public function show(Request $request, Item $item): Response
    {
        Gate::authorize('view', $item);

        return Inertia::render('Koleksi/Detail', [
            'item' => ItemPresenter::present($item, $this->calc),
            'photos' => $item->attachments()->get(['id', 'original_name'])->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'url' => '/lampiran/'.$a->id,
            ]),
        ]);
    }

    public function edit(Request $request, Item $item): Response
    {
        Gate::authorize('update', $item);

        return Inertia::render('Koleksi/Edit', [
            'item' => ItemPresenter::present($item, $this->calc),
        ]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        Gate::authorize('update', $item);

        $data = $this->validated($request, $item->kind, false);

        DB::transaction(function () use ($request, $item, $data) {
            $item->update($this->itemAttributes($data, $item->kind));
            $this->syncUnits($item, $data, $item->kind);
            if ($request->hasFile('photo')) {
                $item->attachPhoto($request->file('photo'), $request->user()->id);
            }
        });

        return redirect('/koleksi/'.$item->id)->with('toast', ['type' => 'success', 'message' => 'Perubahan disimpan']);
    }

    public function destroy(Item $item): RedirectResponse
    {
        Gate::authorize('delete', $item);

        foreach ($item->attachments as $attachment) {
            Storage::disk('local')->delete($attachment->path);
        }
        $item->delete();

        return redirect('/koleksi')->with('toast', ['type' => 'success', 'message' => 'Barang dihapus']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $kind, bool $old): array
    {
        foreach (self::DECIMAL_FIELDS as $field) {
            if (is_string($request->input($field))) {
                $request->merge([$field => str_replace(',', '.', trim($request->input($field)))]);
            }
        }

        $dateCertainty = ['exact', 'approximate', 'unknown'];

        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'brand' => ['nullable', Rule::in(self::BRANDS)],
            'ownership' => ['required', 'in:own,held_for_others'],
            'held_for' => ['nullable', 'required_if:ownership,held_for_others', 'string', 'max:160'],
            'purity_input' => ['nullable', 'numeric'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'purchase_store' => ['nullable', 'string', 'max:120'],
            'resale_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'owned_since' => ['nullable', 'date', 'before_or_equal:today'],
            'owned_since_certainty' => ['required', Rule::in($dateCertainty)],
            'haul_start' => ['nullable', 'date', 'before_or_equal:today'],
            'haul_start_certainty' => ['nullable', 'required_with:haul_start', Rule::in($dateCertainty)],
            'haul_start_note' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];

        if ($old) {
            // An old collection exists to capture when it was owned from, so the date or an honest "unknown" is required.
            $rules['owned_since'] = ['nullable', 'required_unless:owned_since_certainty,unknown', 'date', 'before_or_equal:today'];
        }

        if ($kind === 'bar') {
            $rules += [
                'unit_weight_g' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
                'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            ];
        } else {
            $rules += [
                'jewelry_type' => ['nullable', 'in:cincin,kalung,gelang,anting,liontin,lainnya'],
                'gross_weight_g' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
                'stone_state' => ['required', 'in:none,known,unknown'],
                'stone_weight_g' => ['nullable', 'required_if:stone_state,known', 'numeric', 'gte:0', 'max:1000000'],
                'net_weight_g' => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
                'purpose' => ['nullable', 'in:worn,investment,undecided'],
            ];
        }

        $data = $request->validate($rules);

        // Purity is entered in karat only.
        if (($data['purity_input'] ?? null) !== null) {
            $data['purity_source'] = 'karat';
            try {
                $data['purity_fraction'] = GoldCalculator::purityFraction('karat', (string) $data['purity_input']);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['purity_input' => 'Kadar di luar rentang untuk satuan yang dipilih.']);
            }
        } else {
            $data['purity_fraction'] = null;
            $data['purity_source'] = null;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function itemAttributes(array $data, string $kind): array
    {
        $attributes = collect($data)->only([
            'name', 'brand', 'ownership', 'held_for', 'purity_fraction', 'purity_source', 'purity_input',
            'acquisition_cost', 'purchase_store', 'resale_discount_percent', 'owned_since', 'owned_since_certainty',
            'haul_start', 'haul_start_certainty', 'haul_start_note', 'notes',
        ])->all();

        $attributes['kind'] = $kind;
        if ($attributes['ownership'] !== 'held_for_others') {
            $attributes['held_for'] = null;
        }

        if ($kind === 'bar') {
            $attributes['unit_weight_g'] = $data['unit_weight_g'] ?? null;
            $attributes['resale_discount_percent'] = null; // only jewelry has a resale discount
        } else {
            $attributes += collect($data)->only(['jewelry_type', 'gross_weight_g', 'stone_state', 'net_weight_g', 'purpose'])->all();
            // Unknown stays null, never zero; the weight only counts when the stone is stated as known.
            $attributes['stone_weight_g'] = ($data['stone_state'] === 'known') ? $data['stone_weight_g'] : null;
        }

        return $attributes;
    }

    private function syncUnits(Item $item, array $data, string $kind): void
    {
        if ($kind !== 'bar') {
            return;
        }

        // Older items may still carry serial numbers; leave them alone while the number of pieces is unchanged.
        $pieces = (int) $data['quantity'];
        if ($item->units()->whereNotNull('serial_number')->exists() && (int) $item->units()->sum('quantity') === $pieces) {
            return;
        }

        $item->units()->delete();
        $item->units()->create(['serial_number' => null, 'quantity' => $pieces]);
    }
}
