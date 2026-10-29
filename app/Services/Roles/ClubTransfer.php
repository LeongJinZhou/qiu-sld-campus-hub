<?php

namespace App\Services\Roles;

use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\Club;
use App\Services\AuditLogger;
use App\Services\Time\SessionResolver;
use DomainException;

/** SLD sets a club up, then hands it to its committee and advisor; SLD can take it back (CLAUDE.md §5). */
class ClubTransfer
{
    public function __construct(
        private readonly CompulsoryPositionsCheck $compulsory,
        private readonly SessionResolver $sessions,
        private readonly AuditLogger $audit,
    ) {}

    public function transfer(Club $club): void
    {
        $session = $this->sessions->current();
        $this->compulsory->assertComplete(ScopeType::Club, $club->id, $session->id);
        if (! $club->roleAssignments()->where('session_id', $session->id)->where('role', ScopedRole::Advisor)->exists()) {
            throw new DomainException('Add an advisor before transferring the club.');
        }

        $club->update(['managed_by' => 'committee', 'transferred_at' => now()]);
        $this->audit->log('club.transferred', $club, ['session' => $session->name]);
    }

    public function takeBack(Club $club): void
    {
        $club->update(['managed_by' => 'sld', 'transferred_at' => null]);
        $this->audit->log('club.taken_back', $club);
    }
}
