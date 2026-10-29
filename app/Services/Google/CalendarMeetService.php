<?php

namespace App\Services\Google;

use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Creates Google Calendar events with a Meet link on the organiser's own QIU account (CLAUDE.md §2),
 * inviting the attendees. Tokens are stored encrypted and refreshed when they expire.
 */
class CalendarMeetService
{
    public const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    private const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function isConnected(User $user): bool
    {
        return $user->google_refresh_token !== null || ($user->google_token !== null && $user->google_token_expires_at?->isFuture());
    }

    /**
     * @param  list<string>  $attendeeEmails
     * @return array{link: string, event_id: string}
     */
    public function createMeeting(User $organiser, string $title, CarbonInterface $start, CarbonInterface $end, array $attendeeEmails, ?string $description = null): array
    {
        $response = Http::withToken($this->accessToken($organiser))
            ->post(self::EVENTS_URL.'?conferenceDataVersion=1&sendUpdates=all', [
                'summary' => $title,
                'description' => $description,
                'start' => ['dateTime' => $start->toIso8601String(), 'timeZone' => config('app.timezone')],
                'end' => ['dateTime' => $end->toIso8601String(), 'timeZone' => config('app.timezone')],
                'attendees' => array_map(fn (string $email) => ['email' => $email], array_values(array_unique($attendeeEmails))),
                'conferenceData' => ['createRequest' => ['requestId' => (string) Str::uuid(), 'conferenceSolutionKey' => ['type' => 'hangoutsMeet']]],
            ]);

        if (! $response->successful() || ! $response->json('hangoutLink')) {
            throw new DomainException('Google Calendar did not create the Meet link. Reconnect your Google calendar and try again.');
        }

        return ['link' => (string) $response->json('hangoutLink'), 'event_id' => (string) $response->json('id')];
    }

    private function accessToken(User $user): string
    {
        if ($user->google_token && $user->google_token_expires_at?->gt(now()->addMinute())) {
            return $user->google_token;
        }
        if (! $user->google_refresh_token) {
            throw new DomainException('Connect your QIU Google calendar first.');
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $user->google_refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        if (! $response->successful()) {
            throw new DomainException('Your Google calendar connection expired. Connect it again.');
        }
        $user->forceFill([
            'google_token' => $response->json('access_token'),
            'google_token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ])->save();

        return (string) $user->google_token;
    }
}
