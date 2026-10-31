<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use DomainException;

/** Signing up for an event: by the student themselves, or by a committee member scanning their personal QR. */
class EventRegistrations
{
    public function register(Event $event, User $user): EventRegistration
    {
        if (! $event->registration_open || $event->ends_at->isPast()) {
            throw new DomainException('Registration is closed for this event.');
        }
        if (! $user->student?->isActive()) {
            throw new DomainException("{$user->name} is not an active QIU student, so cannot register.");
        }

        // A paid event keeps the place as "payment pending" until the fee is confirmed.
        return $event->registrations()->firstOrCreate(['user_id' => $user->id], ['status' => $event->fee_sen > 0 ? 'pending_payment' : 'registered']);
    }
}
