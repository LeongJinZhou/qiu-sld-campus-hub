<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated staff account (with no active student record) is signed out on its next request, so disabling an
 * account takes effect immediately rather than when its session expires.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Deactivating a staff account ends its access, SLD admins included. The only exception is an emergency super
        // admin listed in SUPERADMIN_EMAILS (server configuration), who cannot be locked out from the app.
        $emergency = $user && in_array(strtolower($user->email), array_map('strtolower', config('sld.superadmin_emails')), true);
        if ($user && $user->staff && ! $user->staff->active && ! $user->student?->isActive() && ! $emergency) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'This account has been disabled.'], 403)
                : redirect()->route('login')->with('error', 'This account has been disabled. Contact SLD if this is a mistake.');
        }

        return $next($request);
    }
}
