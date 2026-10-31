<?php

namespace App\Policies;

use App\Enums\ScopedRole;
use App\Models\Club;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Roles\RoleService;
use App\Services\Time\SessionResolver;

class ClubPolicy
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly SessionResolver $sessions,
    ) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Club $club): bool
    {
        return true;
    }

    /** SLD setup: create clubs, assign committees and advisors, transfer and take back. */
    public function manage(User $user): bool
    {
        return $user->isSldAdmin();
    }

    /** Editing the club profile: SLD always; committee and advisor only after the club is transferred to them. */
    public function update(User $user, Club $club): bool
    {
        return $user->isSldAdmin()
            || ($club->isTransferred() && $this->roles->belongsToClub($user, $club, $this->sessions->current()));
    }

    /** Posts, stories, highlights, gallery and FAQ: the club's own people and SLD. */
    public function publish(User $user, Club $club): bool
    {
        return $this->viewInternal($user, $club);
    }

    /**
     * Fees, payment review and the ledger: any committee member of the current session (not only the Treasurer)
     * and SLD. Every action is recorded against the person who did it.
     */
    public function treasure(User $user, Club $club): bool
    {
        return $user->isSldAdmin() || RoleAssignment::where('scope_type', 'club')->where('scope_id', $club->id)
            ->where('session_id', $this->sessions->current()->id)->where('role', ScopedRole::ClubCommittee)->where('user_id', $user->id)->exists();
    }

    /** Members, documents and finance tabs (later phases): committee, advisor and SLD only. */
    public function viewInternal(User $user, Club $club): bool
    {
        return $user->isSldAdmin() || $this->roles->belongsToClub($user, $club, $this->sessions->current());
    }
}
