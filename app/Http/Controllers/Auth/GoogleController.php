<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\NotInRecordsException;
use App\Http\Controllers\Controller;
use App\Services\Auth\SignInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')
            ->with(['hd' => config('services.google.hosted_domain'), 'prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(SignInService $signIn): RedirectResponse
    {
        $google = Socialite::driver('google')->user();

        // `hd` on the redirect is only a hint; the hosted-domain claim is checked server-side too.
        if (($google->user['hd'] ?? null) !== config('services.google.hosted_domain')) {
            return to_route('not-in-records');
        }

        try {
            $user = $signIn->signIn((string) $google->getEmail(), (string) $google->getName(), $google->getAvatar(), (string) $google->getId());
        } catch (NotInRecordsException) {
            return to_route('not-in-records');
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('feed'));
    }
}
