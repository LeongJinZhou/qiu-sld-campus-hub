<?php

namespace App\Services\Roles;

use App\Enums\ScopeType;
use App\Models\RoleAssignment;
use DomainException;

/** A club or event cannot submit any document until all four compulsory positions are held by active students. */
class CompulsoryPositionsCheck
{
    public const POSITIONS = ['President', 'Vice President', 'Secretary', 'Treasurer'];

    /** A workshop needs no fixed offices, only this many persons in charge. */
    public const PERSONS_IN_CHARGE = 2;

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

    /** @return list<string> what an event's own committee still lacks before its proposal can be submitted */
    public function missingForEvent(int $eventId, bool $workshop): array
    {
        if (! $workshop) {
            return $this->missing(ScopeType::Event, $eventId, null);
        }
        $people = RoleAssignment::query()->with('user.student')->where('scope_type', ScopeType::Event)->where('scope_id', $eventId)->get()
            ->filter(fn (RoleAssignment $a) => $a->user->student?->isActive() === true)->unique('user_id')->count();

        return $people >= self::PERSONS_IN_CHARGE ? [] : [sprintf('at least %d persons in charge (%d added)', self::PERSONS_IN_CHARGE, $people)];
    }

    public function assertEventComplete(int $eventId, bool $workshop): void
    {
        $missing = $this->missingForEvent($eventId, $workshop);
        if ($missing !== []) {
            throw new DomainException('Complete the event committee first: '.implode(', ', $missing).'.');
        }
    }

    public function assertComplete(ScopeType $scopeType, int $scopeId, ?int $sessionId): void
    {
        $missing = $this->missing($scopeType, $scopeId, $sessionId);
        if ($missing !== []) {
            throw new DomainException('Fill the compulsory positions first: '.implode(', ', $missing).'.');
        }
    }
}
