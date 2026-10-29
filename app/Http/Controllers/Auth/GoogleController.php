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

    /** Alumni sign in with the personal Google account they linked on their profile. */
    public function alumni(): SymfonyRedirect
    {
        session()->put('alumni_sign_in', true);

        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(SignInService $signIn): RedirectResponse
    {
        $google = Socialite::driver('google')->user();
        $alumni = (bool) session()->pull('alumni_sign_in', false);

        try {
            if ($alumni) {
                // Google must have verified the address before it can stand in for the QIU account.
                if (($google->user['email_verified'] ?? false) !== true) {
                    return to_route('not-in-records');
                }
                $user = $signIn->signInWithPersonalEmail((string) $google->getEmail());
            } elseif (($google->user['hd'] ?? null) !== config('services.google.hosted_domain')) {
                // `hd` on the redirect is only a hint; the hosted-domain claim is checked server-side too.
                return to_route('not-in-records');
            } else {
                $user = $signIn->signIn((string) $google->getEmail(), (string) $google->getName(), $google->getAvatar(), (string) $google->getId());
            }
        } catch (NotInRecordsException) {
            return to_route('not-in-records');
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('feed'));
    }
}
