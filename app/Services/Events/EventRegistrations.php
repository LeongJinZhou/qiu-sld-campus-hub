<?php

namespace App\Services\Events;

use App\Enums\MembershipStatus;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\User;
use App\Services\Roles\RoleService;
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
        if (! $this->mayJoin($event, $user)) {
            throw new DomainException("{$event->name} is for {$event->club->name} members only. Join the club first, then register.");
        }

        // A paid event keeps the place as "payment pending" until the fee is confirmed.
        return $event->registrations()->firstOrCreate(['user_id' => $user->id], ['status' => $event->fee_sen > 0 ? 'pending_payment' : 'registered']);
    }

    /** Members-only events take the club's members (active or renewing) and its committee; open events take anyone. */
    public function mayJoin(Event $event, User $user): bool
    {
        return ! $event->members_only
            || Membership::where('club_id', $event->club_id)->where('user_id', $user->id)->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace])->exists()
            || app(RoleService::class)->belongsToClub($user, $event->club, $event->session);
    }
}
