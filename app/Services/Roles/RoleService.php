<?php

namespace App\Services\Roles;

use App\Enums\GlobalRole;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Puts an active student into a club position for the session, replacing the current holder.
     * One person holds at most one position per club per session.
     */
    public function assignClubPosition(Club $club, AcademicSession $session, User $user, string $position): RoleAssignment
    {
        $position = trim($position);
        if ($position === '') {
            throw new DomainException('Position is required.');
        }
        if (! $user->student?->isActive()) {
            throw new DomainException("{$user->name} is not an active student.");
        }

        $scope = $this->scope(ScopeType::Club, $club->id, $session->id, ScopedRole::ClubCommittee);
        $existing = (clone $scope)->where('user_id', $user->id)->first();
        if ($existing && $existing->position !== $position) {
            throw new DomainException("{$user->name} already holds {$existing->position} in {$club->name} for {$session->name}.");
        }

        return DB::transaction(function () use ($scope, $club, $session, $user, $position): RoleAssignment {
            (clone $scope)->where('position', $position)->delete();
            $assignment = RoleAssignment::create([
                'user_id' => $user->id,
                'role' => ScopedRole::ClubCommittee,
                'scope_type' => ScopeType::Club,
                'scope_id' => $club->id,
                'session_id' => $session->id,
                'position' => $position,
                'is_compulsory' => in_array($position, CompulsoryPositionsCheck::POSITIONS, true),
                'starts_on' => $session->starts_on,
                'ends_on' => $session->ends_on,
            ]);
            $this->audit->log('committee.assigned', $club, ['session' => $session->name, 'position' => $position, 'user_id' => $user->id]);

            return $assignment;
        });
    }

    public function addAdvisor(Club $club, AcademicSession $session, User $user): RoleAssignment
    {
        if (! $user->hasRole(GlobalRole::Staff->value)) {
            throw new DomainException("{$user->name} needs a staff account to be an advisor.");
        }
        if ($this->scope(ScopeType::Club, $club->id, $session->id, ScopedRole::Advisor)->where('user_id', $user->id)->exists()) {
            throw new DomainException("{$user->name} is already an advisor for {$session->name}.");
        }

        $assignment = RoleAssignment::create([
            'user_id' => $user->id,
            'role' => ScopedRole::Advisor,
            'scope_type' => ScopeType::Club,
            'scope_id' => $club->id,
            'session_id' => $session->id,
            'starts_on' => $session->starts_on,
            'ends_on' => $session->ends_on,
        ]);
        $this->audit->log('advisor.added', $club, ['session' => $session->name, 'user_id' => $user->id]);

        return $assignment;
    }

    public function remove(RoleAssignment $assignment): void
    {
        $assignment->delete();
        $this->audit->log('role.removed', $assignment, [
            'role' => $assignment->role->value,
            'scope' => $assignment->scope_type->value.':'.$assignment->scope_id,
            'position' => $assignment->position,
            'user_id' => $assignment->user_id,
        ]);
    }

    public function grantGlobal(User $user, GlobalRole $role): void
    {
        if (! $user->hasRole($role->value)) {
            $user->assignRole($role->value);
            $this->audit->log('role.granted', $user, ['role' => $role->value]);
        }
    }

    public function revokeGlobal(User $user, GlobalRole $role): void
    {
        if ($user->hasRole($role->value)) {
            $user->removeRole($role->value);
            $this->audit->log('role.revoked', $user, ['role' => $role->value]);
        }
    }

    /** Whether the user is on the club's committee or advises it in the session. */
    public function belongsToClub(User $user, Club $club, AcademicSession $session): bool
    {
        return RoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('scope_type', ScopeType::Club)
            ->where('scope_id', $club->id)
            ->where('session_id', $session->id)
            ->exists();
    }

    /** @return Builder<RoleAssignment> */
    private function scope(ScopeType $type, int $scopeId, ?int $sessionId, ScopedRole $role)
    {
        return RoleAssignment::query()
            ->where('scope_type', $type)
            ->where('scope_id', $scopeId)
            ->where('session_id', $sessionId)
            ->where('role', $role);
    }
}
