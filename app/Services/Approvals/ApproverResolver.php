<?php

namespace App\Services\Approvals;

use App\Enums\ChainRole;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\Document;
use App\Models\RoleAssignment;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Collection;

/** Who may act on a chain step, and the position printed under their signature. */
class ApproverResolver
{
    public function canAct(User $user, Document $document, ChainRole $role): bool
    {
        return $this->candidates($document, $role)->contains('id', $user->id);
    }

    /** @return Collection<int, User> */
    public function candidates(Document $document, ChainRole $role): Collection
    {
        return match ($role) {
            ChainRole::Preparer => $this->clubRoles($document, ScopedRole::ClubCommittee)->pluck('user'),
            ChainRole::Secretary => $this->clubRoles($document, ScopedRole::ClubCommittee, 'Secretary')->pluck('user'),
            ChainRole::President => $this->clubRoles($document, ScopedRole::ClubCommittee, 'President')->pluck('user'),
            ChainRole::Advisor => $this->clubRoles($document, ScopedRole::Advisor)->pluck('user'),
            ChainRole::SldOfficer => User::role(['admin', 'superadmin'])->get(),
            default => User::whereIn('id', Staff::where('active', true)->where('approver_role', $role->approverRole())->select('user_id'))->get(),
        };
    }

    public function positionFor(User $user, Document $document, ChainRole $role): string
    {
        return match ($role) {
            ChainRole::Preparer => $this->clubRoles($document, ScopedRole::ClubCommittee)->firstWhere('user_id', $user->id)->position ?? 'Committee',
            ChainRole::Advisor => 'Club Advisor',
            default => $role->label(),
        };
    }

    /** @return Collection<int, RoleAssignment> */
    private function clubRoles(Document $document, ScopedRole $role, ?string $position = null): Collection
    {
        return RoleAssignment::with('user')
            ->where('scope_type', ScopeType::Club)
            ->where('scope_id', $document->club_id)
            ->where('session_id', $document->session_id)
            ->where('role', $role)
            ->when($position, fn ($q) => $q->where('position', $position))
            ->get();
    }
}
