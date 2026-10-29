<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Services\Google\CalendarMeetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/** Incremental consent for the Calendar scope, asked for only when someone first creates a Meet link. */
class GoogleCalendarController extends Controller
{
    public function connect(Request $request): SymfonyRedirect
    {
        $request->session()->put('calendar.return', url()->previous());

        return Socialite::driver('google')
            ->redirectUrl(route('google.calendar.callback'))
            ->scopes([CalendarMeetService::SCOPE])
            ->with([
                'access_type' => 'offline', 'prompt' => 'consent', 'include_granted_scopes' => 'true',
                'hd' => config('services.google.hosted_domain'), 'login_hint' => $request->user()->email,
            ])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $google = Socialite::driver('google')->redirectUrl(route('google.calendar.callback'))->user();
        $back = $request->session()->pull('calendar.return', route('feed'));

        // The calendar must belong to the signed-in QIU account.
        if (strcasecmp((string) $google->getEmail(), $request->user()->email) !== 0) {
            return redirect($back)->with('error', 'Please connect the calendar of the QIU account you are signed in with.');
        }

        $request->user()->forceFill([
            'google_token' => $google->token,
            'google_refresh_token' => $google->refreshToken ?? $request->user()->google_refresh_token,
            'google_token_expires_at' => now()->addSeconds((int) ($google->expiresIn ?? 3600)),
        ])->save();

        return redirect($back)->with('success', 'Google Calendar connected. You can now create Meet links.');
    }
}
