<?php

namespace App\Services\Calendar;

use App\Enums\DocumentStatus;
use App\Enums\MembershipStatus;
use App\Models\Agm;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The campus calendar: every event whose proposal is fully approved, for everyone. Month views go back to the
 * first event ever; a private iCalendar feed lets anyone subscribe from Google Calendar.
 */
class EventCalendar
{
    /** @return Collection<int, Event> approved events overlapping [from, to) */
    public function between(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Event::with('club')->where('status', DocumentStatus::Approved)
            ->where('starts_at', '<', $to)->where('ends_at', '>=', $from)
            ->orderBy('starts_at')->get();
    }

    /** @return array<string, mixed> */
    public function present(Event $event, User $viewer, array $mine, array $followed): array
    {
        return [
            'slug' => $event->slug,
            'name' => $event->name,
            'club' => $event->club->name,
            'club_slug' => $event->club->slug,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'venue' => $event->venue,
            'poster_url' => $event->posterUrl(),
            'fee_sen' => $event->fee_sen,
            'registration_open' => (bool) $event->registration_open && $event->ends_at->isFuture(),
            'registered' => in_array($event->id, $mine, true),
            'followed' => in_array($event->club_id, $followed, true),
            'google_url' => $this->googleLink($event),
        ];
    }

    /**
     * The rest of a person's calendar, shown only to those it concerns: meetings and weekly-report due dates for the
     * committee and advisor, weekly activities and AGMs for members too, every AGM for SLD.
     *
     * @return list<array{key: string, kind: string, title: string, club: string, starts_at: string, ends_at: string, url: string}>
     */
    public function personal(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $session = app(SessionResolver::class)->current();
        $runs = RoleAssignment::query()->acting($session, training: true)->where('user_id', $user->id)->pluck('scope_id')->unique();
        $member = Membership::where('user_id', $user->id)->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace])->pluck('club_id');
        $clubs = $runs->merge($member)->unique();
        $items = [];
        foreach (Meeting::with('club', 'minutes')->whereIn('club_id', $clubs)->where('starts_at', '<', $to)->where('ends_at', '>=', $from->subDays(8))->get() as $m) {
            $isCommittee = $runs->contains($m->club_id);
            if ($m->kind === 'committee' && ! $isCommittee) {
                continue;
            }
            if ($m->ends_at->gte($from)) {
                $items[] = ['key' => "m{$m->id}", 'kind' => $m->kind === 'activity' ? 'activity' : 'meeting', 'title' => $m->title, 'club' => $m->club->name,
                    'starts_at' => $m->starts_at->toIso8601String(), 'ends_at' => $m->ends_at->toIso8601String(), 'url' => route('meetings.show', $m)];
            }
            // The weekly activity report is due a week after the activity, until it is submitted.
            $due = $m->ends_at->copy()->addDays(7)->setTime(23, 59);
            if ($m->kind === 'activity' && $isCommittee && ! in_array($m->minutes?->status, [DocumentStatus::InReview, DocumentStatus::Approved], true) && $due->between($from, $to)) {
                $items[] = ['key' => "r{$m->id}", 'kind' => 'report_due', 'title' => "Weekly report due: {$m->title}", 'club' => $m->club->name,
                    'starts_at' => $due->copy()->setTime(23, 0)->toIso8601String(), 'ends_at' => $due->toIso8601String(), 'url' => route('meetings.show', $m)];
            }
        }
        $agms = Agm::with('club')->where('starts_at', '<', $to)->where('ends_at', '>=', $from)->when(! $user->isSldAdmin(), fn ($q) => $q->whereIn('club_id', $clubs))->get();
        foreach ($agms as $a) {
            $items[] = ['key' => "a{$a->id}", 'kind' => 'agm', 'title' => "AGM · {$a->club->name}", 'club' => $a->club->name,
                'starts_at' => $a->starts_at->toIso8601String(), 'ends_at' => $a->ends_at->toIso8601String(), 'url' => route('agm.show', $a->club)];
        }

        return $items;
    }

    /** "Add to Google Calendar" for one event. */
    public function googleLink(Event $event): string
    {
        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => "{$event->name} ({$event->club->name})",
            'dates' => $this->utc($event->starts_at).'/'.$this->utc($event->ends_at),
            'details' => trim(($event->summary ? $event->summary."\n\n" : '').route('events.show', $event)),
            'location' => (string) $event->venue,
        ]);
    }

    /** iCalendar (RFC 5545) of approved events from a year ago onwards. */
    public function ics(): string
    {
        $events = $this->between(CarbonImmutable::now()->subYear(), CarbonImmutable::now()->addYears(2));
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//QIU SLD Campus Hub//Events//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:QIU Campus Events', 'X-WR-TIMEZONE:Asia/Kuala_Lumpur', 'REFRESH-INTERVAL;VALUE=DURATION:PT6H'];
        foreach ($events as $event) {
            $lines = [...$lines, 'BEGIN:VEVENT', "UID:event-{$event->id}@".parse_url((string) config('app.url'), PHP_URL_HOST),
                'DTSTAMP:'.$this->utc($event->updated_at ?? now()), 'DTSTART:'.$this->utc($event->starts_at), 'DTEND:'.$this->utc($event->ends_at),
                'SUMMARY:'.$this->escape("{$event->name} ({$event->club->name})"), 'LOCATION:'.$this->escape((string) $event->venue),
                'DESCRIPTION:'.$this->escape(trim(($event->summary ? $event->summary."\n\n" : '').route('events.show', $event))),
                'URL:'.route('events.show', $event), 'END:VEVENT'];
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn (string $l) => $this->fold($l), $lines))."\r\n";
    }

    private function utc(\DateTimeInterface $at): string
    {
        return CarbonImmutable::instance($at)->utc()->format('Ymd\THis\Z');
    }

    private function escape(string $text): string
    {
        // Every line break (CRLF, LF or a bare CR) becomes an escaped "\n", so text can never start a new property.
        return str_replace(['\\', ';', ',', "\r\n", "\r", "\n"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $text);
    }

    /** Lines longer than 75 octets continue on the next line, starting with a space. */
    private function fold(string $line): string
    {
        $out = '';
        while (strlen($line) > 75) {
            $cut = 75;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }
            $out .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
        }

        return $out.$line;
    }
}
