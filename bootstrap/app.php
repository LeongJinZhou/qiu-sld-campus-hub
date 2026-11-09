<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            'throttle:global',
            EnsureAccountActive::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);
        // Behind Firebase Hosting / Cloud Run the request arrives through Google's proxies (HTTPS, client IP).
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
        $middleware->redirectUsersTo(fn () => route('feed'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // PHP drops the body when it exceeds post_max_size; say so instead of showing a bare error page.
        $exceptions->render(fn (PostTooLargeException $e, Request $request) => $request->expectsJson()
            ? response()->json(['message' => 'That file is too large.'], 413)
            : back()->with('error', 'That file is too large. Event documents may be up to 50 MB, photos up to 8 MB.'));
    })->create();
