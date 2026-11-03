<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening. The camera is allowed for our own pages only (QR scanning and cash-handover photos).
 * Outside local development a Content-Security-Policy allows scripts only from this site (plus the one inline
 * theme script, by nonce), so injected markup cannot run code or send data elsewhere.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $csp = ! app()->environment('local');
        if ($csp) {
            Vite::useCspNonce();
        }
        $response = $next($request);
        if ($csp) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-".Vite::cspNonce()."'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob: https:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "media-src 'self' blob:",
                "worker-src 'self' blob:",
                "frame-src 'none'",
                "frame-ancestors 'none'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self' https://accounts.google.com",
            ]));
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        }
        $response->headers->remove('X-Powered-By');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
