<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In a subfolder (Plesk: /campus-hub) Laravel writes the home URL without its last slash ("/campus-hub"), and the
 * web server only sends "/campus-hub/" to the app, so a redirect to the sign-in page would end on a 404.
 * Redirects to the home page therefore always carry the slash. At a domain root nothing changes.
 */
class MountedRootRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $mounted = trim((string) parse_url(url('/'), PHP_URL_PATH), '/') !== '';
        if (! $mounted || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $home = rtrim(url('/'), '/');
        $target = $response->getTargetUrl();
        if ($target === $home || str_starts_with($target, $home.'?') || str_starts_with($target, $home.'#')) {
            $response->setTargetUrl($home.'/'.substr($target, strlen($home)));
        }

        return $response;
    }
}
