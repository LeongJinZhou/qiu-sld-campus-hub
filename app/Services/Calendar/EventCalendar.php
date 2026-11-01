<?php

namespace App\Services\Calendar;

use App\Enums\DocumentStatus;
use App\Models\Event;
use App\Models\User;
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
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
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
