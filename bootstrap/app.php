<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Caddy on the host terminates HTTPS and proxies to the container, so trust its forwarded headers.
        // Without this Laravel builds http:// URLs behind the proxy (mixed content, insecure cookies).
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [HandleInertiaRequests::class]);
        $middleware->alias(['admin' => EnsureAdmin::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/ringkasan');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->header('X-Inertia') && ! $request->acceptsHtml()) {
                return $response;
            }
            if (in_array($response->getStatusCode(), [403, 404], true)) {
                // Error responses bypass the web middleware, so shared props are absent: pass what the page needs.
                return Inertia::render('Errors/NotFound', ['signed_in' => $request->user() !== null])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
