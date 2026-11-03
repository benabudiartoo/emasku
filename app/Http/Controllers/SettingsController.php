<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Pengaturan/Index');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['hide_amounts' => ['required', 'boolean']]);

        $request->user()->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => 'Pengaturan disimpan']);
    }
}
