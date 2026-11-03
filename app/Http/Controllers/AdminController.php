<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\User;
use App\Models\ZakatPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The light admin page: who uses the app and how much, as counts only. It never reads an item's name, weight, value,
 * location, note, photo, or a zakat result, and it never sends a password.
 */
class AdminController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $since = now()->subDays(30);

        $users = User::query()
            ->withCount(['items', 'zakatPeriods'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Admin/Index', [
            'totals' => [
                'users' => User::count(),
                'active_30d' => User::where('last_login_at', '>=', $since)->count(),
                'new_30d' => User::where('created_at', '>=', $since)->count(),
                'items' => Item::count(),
                'zakat_periods' => ZakatPeriod::count(),
            ],
            'users' => [
                'data' => $users->getCollection()->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'is_admin' => $u->is_admin,
                    'created_at' => $u->created_at?->format('Y-m-d'),
                    'last_login_at' => $u->last_login_at?->format('Y-m-d H:i'),
                    'items_count' => $u->items_count,
                    'zakat_periods_count' => $u->zakat_periods_count,
                ])->values(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'prev_url' => $users->previousPageUrl(),
                'next_url' => $users->nextPageUrl(),
            ],
        ]);
    }
}
