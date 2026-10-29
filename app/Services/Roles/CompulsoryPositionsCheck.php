<?php

namespace App\Services\Roles;

use App\Enums\ScopeType;
use App\Models\RoleAssignment;
use DomainException;

/** A club or event cannot submit any document until all four compulsory positions are held by active students. */
class CompulsoryPositionsCheck
{
    public const POSITIONS = ['President', 'Vice President', 'Secretary', 'Treasurer'];

    /** @return list<string> compulsory positions that are vacant or held by someone no longer active */
    public function missing(ScopeType $scopeType, int $scopeId, ?int $sessionId): array
    {
        $held = RoleAssignment::query()
            ->with('user.student')
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('session_id', $sessionId)
            ->whereIn('position', self::POSITIONS)
            ->get()
            ->filter(fn (RoleAssignment $a) => $a->user->student?->isActive() === true)
            ->pluck('position')
            ->all();

        return array_values(array_diff(self::POSITIONS, $held));
    }

    public function assertComplete(ScopeType $scopeType, int $scopeId, ?int $sessionId): void
    {
        $missing = $this->missing($scopeType, $scopeId, $sessionId);
        if ($missing !== []) {
            throw new DomainException('Fill the compulsory positions first: '.implode(', ', $missing).'.');
        }
    }
}
