<?php

namespace App\Services\Roles;

use App\Enums\ScopedRole;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\RoleAssignment;
use Illuminate\Support\Collection;

/** Committee + advisor cards for a club's Committee tab (CLAUDE.md §6 Social). */
class CommitteeBoard
{
    /**
     * @return array{committee: list<array<string, mixed>>, advisors: list<array<string, mixed>>}
     */
    public function for(Club $club, AcademicSession $session): array
    {
        $assignments = $club->roleAssignments()
            ->where('session_id', $session->id)
            ->with(['user.student', 'user.staff'])
            ->get();

        [$advisors, $committee] = $assignments->partition(fn (RoleAssignment $a) => $a->role === ScopedRole::Advisor);

        return [
            'committee' => $this->ordered($committee)->map(fn (RoleAssignment $a) => $this->card($a, $a->position))->values()->all(),
            'advisors' => $advisors->map(fn (RoleAssignment $a) => $this->card($a, 'Advisor'))->values()->all(),
        ];
    }

    /** @return list<array{id: int, name: string}> sessions this club has records for, plus the current one, newest first */
    public function sessions(Club $club, AcademicSession $current): array
    {
        return AcademicSession::query()
            ->whereIn('id', $club->roleAssignments()->select('session_id'))
            ->orWhere('id', $current->id)
            ->orderByDesc('starts_on')
            ->get(['id', 'name'])
            ->map(fn (AcademicSession $s) => ['id' => $s->id, 'name' => $s->name])
            ->all();
    }

    /**
     * Compulsory positions in their official order, then optional ones alphabetically.
     *
     * @param  Collection<int, RoleAssignment>  $committee
     * @return Collection<int, RoleAssignment>
     */
    private function ordered(Collection $committee): Collection
    {
        $rank = array_flip(CompulsoryPositionsCheck::POSITIONS);

        return $committee->sortBy(fn (RoleAssignment $a) => [$rank[$a->position] ?? 99, $a->position]);
    }

    /** @return array<string, mixed> */
    private function card(RoleAssignment $assignment, ?string $position): array
    {
        $user = $assignment->user;
        $student = $user->student;

        return [
            'assignment_id' => $assignment->id,
            'position' => $position,
            'is_compulsory' => $assignment->is_compulsory,
            'name' => $user->name,
            'photo_url' => $user->photoUrl(),
            'programme_short' => $student?->programmeShortForm(),
            'year_group' => $student?->year_group,
            'title' => $user->staff?->title,
            'profile_url' => route('profile.public', $user->qr_token),
        ];
    }
}
