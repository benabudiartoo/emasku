<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => ['user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'hide_amounts' => (bool) ($user->hide_amounts ?? false),
            ] : null],
            'toast' => fn () => $request->session()->get('toast'),
            'new_location_id' => fn () => $request->session()->get('new_location_id'),
        ];
    }
}
