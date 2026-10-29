<?php

namespace App\Services\Events;

use App\Models\Event;

/**
 * Per-event signed, expiring clock-in/clock-out codes. A code is an HMAC of the event, the action and the
 * current minute, so it rotates every minute and the previous minute's code is still accepted.
 */
class AttendanceQr
{
    public const ACTIONS = ['in', 'out'];

    private const SLOT_SECONDS = 60;

    public function code(Event $event, string $action, ?int $at = null): string
    {
        $slot = intdiv($at ?? now()->getTimestamp(), self::SLOT_SECONDS);

        return substr(hash_hmac('sha256', "{$event->id}|{$action}|{$slot}", $event->attendance_secret.config('app.key')), 0, 24);
    }

    public function url(Event $event, string $action): string
    {
        return route('events.attend', ['event' => $event, 'action' => $action, 'code' => $this->code($event, $action)]);
    }

    public function isValid(Event $event, string $action, string $code): bool
    {
        $now = now()->getTimestamp();

        return in_array($action, self::ACTIONS, true) && (
            hash_equals($this->code($event, $action, $now), $code)
            || hash_equals($this->code($event, $action, $now - self::SLOT_SECONDS), $code)
        );
    }
}
