<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Pengaturan/Lokasi', [
            'locations' => Location::where('user_id', $request->user()->id)->withCount('items')->orderBy('name')->get(['id', 'name', 'kind']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $location = new Location($this->validated($request));
        $location->user_id = $request->user()->id; // not fillable: always from the session
        $location->save();

        return back()
            ->with('toast', ['type' => 'success', 'message' => 'Lokasi disimpan'])
            ->with('new_location_id', $location->id);
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        Gate::authorize('update', $location);

        $location->update($this->validated($request, $location));

        return back()->with('toast', ['type' => 'success', 'message' => 'Lokasi disimpan']);
    }

    public function destroy(Location $location): RedirectResponse
    {
        Gate::authorize('delete', $location);

        if ($location->items()->exists()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Lokasi masih berisi barang. Pindahkan barangnya dulu.']);
        }

        $location->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Lokasi dihapus']);
    }

    /** @return array{name: string, kind: string} */
    private function validated(Request $request, ?Location $location = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                \Illuminate\Validation\Rule::unique('locations')
                    ->where('user_id', $request->user()->id)
                    ->ignore($location?->id),
            ],
            'kind' => ['required', 'in:home,safe,bank_box,other'],
        ]);
    }
}
