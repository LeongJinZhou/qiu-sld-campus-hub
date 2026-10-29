<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\ScopeType;
use App\Models\Club;
use App\Models\Event;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Roles\RoleService;
use App\Services\Time\SessionResolver;

class EventPolicy
{
    public function __construct(private readonly RoleService $roles, private readonly SessionResolver $sessions) {}

    /** Club committee (current session) or SLD can start an event proposal. */
    public function create(User $user, Club $club): bool
    {
        return $user->isSldAdmin() || $this->roles->belongsToClub($user, $club, $this->sessions->current());
    }

    /** Approved events are public to signed-in users; drafts only to the organisers, advisors, approvers and SLD. */
    public function view(User $user, Event $event): bool
    {
        return $event->status === DocumentStatus::Approved || $this->organises($user, $event) || $user->staff()->exists();
    }

    public function update(User $user, Event $event): bool
    {
        return $this->organises($user, $event);
    }

    /** Club committee, advisor, event committee or SLD. */
    public function organises(User $user, Event $event): bool
    {
        return $user->isSldAdmin()
            || $this->roles->belongsToClub($user, $event->club, $event->session)
            || RoleAssignment::where('scope_type', ScopeType::Event)->where('scope_id', $event->id)->where('user_id', $user->id)->exists();
    }
}
