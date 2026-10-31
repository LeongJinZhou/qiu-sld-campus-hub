<?php

namespace App\Services\Events;

use App\Models\Agm;
use App\Models\Event;

/**
 * Per-event (or per-AGM) signed, expiring clock-in/clock-out codes. A code is an HMAC of the event, the action
 * and the current time slot. The slot length is the event's own setting (10 s to 10 min, AGMs use a minute), and
 * the previous slot's code is still accepted so someone mid-scan is not refused.
 */
class AttendanceQr
{
    public const ACTIONS = ['in', 'out'];

    public const MIN_SECONDS = 10;

    public const MAX_SECONDS = 600;

    private const DEFAULT_SECONDS = 60;

    public function seconds(Event|Agm $event): int
    {
        $seconds = $event instanceof Event ? (int) ($event->qr_rotate_seconds ?: self::DEFAULT_SECONDS) : self::DEFAULT_SECONDS;

        return max(self::MIN_SECONDS, min(self::MAX_SECONDS, $seconds));
    }

    /** Seconds until the code on screen changes. */
    public function secondsLeft(Event|Agm $event): int
    {
        $slot = $this->seconds($event);

        return $slot - (now()->getTimestamp() % $slot);
    }

    public function code(Event|Agm $event, string $action, ?int $at = null): string
    {
        $slot = intdiv($at ?? now()->getTimestamp(), $this->seconds($event));
        $scope = $event instanceof Agm ? "agm:{$event->id}" : $event->id;

        return substr(hash_hmac('sha256', "{$scope}|{$action}|{$slot}", $event->attendance_secret.config('app.key')), 0, 24);
    }

    public function url(Event $event, string $action): string
    {
        return route('events.attend', ['event' => $event, 'action' => $action, 'code' => $this->code($event, $action)]);
    }

    public function isValid(Event|Agm $event, string $action, string $code): bool
    {
        $now = now()->getTimestamp();

        return in_array($action, self::ACTIONS, true) && (
            hash_equals($this->code($event, $action, $now), $code)
            || hash_equals($this->code($event, $action, $now - $this->seconds($event)), $code)
        );
    }
}
