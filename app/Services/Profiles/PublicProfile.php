<?php

namespace App\Services\Profiles;

use App\Enums\ScopeType;
use App\Models\Club;
use App\Models\RoleAssignment;
use App\Models\User;

/**
 * Portfolio-only view of a person (CLAUDE.md §6 Lifelong profile).
 * Never includes IC, phone, address or payment data.
 */
class PublicProfile
{
    /** @return array<string, mixed> */
    public function for(User $user, bool $includeHidden = false): array
    {
        $student = $user->student;
        $show = fn (string $section) => $includeHidden || ! $user->hidesSection($section);

        $assignments = $user->roleAssignments()
            ->where('scope_type', ScopeType::Club)
            ->with('session')
            ->get();
        $clubs = Club::whereIn('id', $assignments->pluck('scope_id'))->get()->keyBy('id');

        return [
            'name' => $user->name,
            'photo_url' => $user->photoUrl(),
            'programme_short' => $student?->programmeShortForm(),
            'programme' => $student?->programme,
            'year_group' => $student?->year_group,
            'title' => $user->staff?->title,
            'clubs' => $show('clubs')
                ? $clubs->map(fn (Club $c) => ['name' => $c->name, 'url' => route('clubs.show', $c)])->values()->all()
                : null,
            'positions' => $show('positions')
                ? $assignments->sortByDesc(fn (RoleAssignment $a) => $a->session?->starts_on)->map(fn (RoleAssignment $a) => [
                    'session' => $a->session?->name,
                    'club' => $clubs[$a->scope_id]->name ?? null,
                    'position' => $a->position ?? 'Advisor',
                ])->values()->all()
                : null,
        ];
    }
}
