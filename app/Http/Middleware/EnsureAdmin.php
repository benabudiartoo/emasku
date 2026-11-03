<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only admins pass. Everyone else gets the "not found" response, not 403, so the page's existence is not revealed.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin === true, 404);

        return $next($request);
    }
}
