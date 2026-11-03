<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    private const DISK = 'local'; // private disk (storage/app/private), never under public/

    public function store(Request $request, Item $item): RedirectResponse
    {
        Gate::authorize('update', $item);

        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $item->attachPhoto($request->file('photo'), $request->user()->id);

        return back();
    }

    public function show(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        abort_unless(Storage::disk(self::DISK)->exists($attachment->path), 404);

        return Storage::disk(self::DISK)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        Gate::authorize('delete', $attachment);

        Storage::disk(self::DISK)->delete($attachment->path);
        $attachment->delete();

        return back();
    }
}
