<?php

namespace App\Services\Profiles;

use App\Enums\MembershipStatus;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\Attendance;
use App\Models\CcaRecord;
use App\Models\Club;
use App\Models\Event;
use App\Models\Membership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Time\SessionResolver;

/**
 * Lifelong portfolio view of a person: photo, programme, year group, clubs, positions per
 * session, events and CCA hours. Never includes IC, phone, address or payment data.
 */
class PublicProfile
{
    public function __construct(private readonly SessionResolver $sessions) {}

    /** @return array<string, mixed> */
    public function for(User $user, bool $includeHidden = false): array
    {
        $student = $user->student;
        $show = fn (string $section) => $includeHidden || ! $user->hidesSection($section);
        $current = $this->sessions->current();

        $assignments = $user->roleAssignments()->whereIn('scope_type', [ScopeType::Club, ScopeType::Event])->with('session')->get();
        $clubAssignments = $assignments->where('scope_type', ScopeType::Club);
        // Clubs they serve on, plus clubs they simply joined as a member (or stayed in as alumni).
        $memberships = Membership::where('user_id', $user->id)->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace, MembershipStatus::Alumni])->get()->keyBy('club_id');
        $clubs = Club::with('category')->whereIn('id', $clubAssignments->pluck('scope_id')->merge($memberships->keys()))->get()->keyBy('id');
        $eventCommittees = Event::whereIn('id', $assignments->where('scope_type', ScopeType::Event)->pluck('scope_id'))->get()->keyBy('id');
        $attended = Attendance::with('event.club')->where('user_id', $user->id)->whereNotNull('clock_in_at')->latest('clock_in_at')->get();
        $cca = CcaRecord::with(['event', 'meeting.club'])->where('user_id', $user->id)->get();

        return [
            'name' => $user->name,
            'photo_url' => $user->photoUrl(),
            'cover_url' => $user->coverUrl(),
            'programme' => $student?->programme,
            'programme_short' => $student?->programmeShortForm(),
            'year_group' => $student?->year_group,
            'status' => $student?->status->value,
            'intake' => $student ? sprintf('%02d/%d', $student->intake_month, $student->year_entry) : null,
            'title' => $user->staff?->title,
            'department' => $user->staff?->department,
            // A hidden section hides its count as well.
            'stats' => [
                'clubs' => $show('clubs') ? $clubs->count() : null,
                'positions' => $show('positions') ? $assignments->where('role', '!=', ScopedRole::Advisor)->count() : null,
                'events' => $show('events') ? $attended->count() : null,
                'cca_hours' => $show('cca') ? round($cca->sum('hours'), 1) : null,
            ],
            'clubs' => $show('clubs') ? $clubs->map(fn (Club $c) => [
                'name' => $c->name,
                'url' => route('clubs.show', $c),
                'logo_url' => $c->logoUrl(),
                'category' => $c->category?->name,
                'current_position' => $clubAssignments->where('scope_id', $c->id)->firstWhere('session_id', $current->id)?->position
                    ?? ($clubAssignments->where('scope_id', $c->id)->firstWhere('session_id', $current->id)?->role === ScopedRole::Advisor ? 'Advisor' : null),
                'membership' => $this->membershipLabel($memberships->get($c->id)),
            ])->sortBy('name')->values()->all() : null,
            'positions' => $show('positions') ? $assignments
                ->sortByDesc(fn (RoleAssignment $a) => $a->session->starts_on ?? $eventCommittees[$a->scope_id]->starts_at ?? null)
                ->map(fn (RoleAssignment $a) => $a->scope_type === ScopeType::Club ? [
                    'session' => $a->session?->name,
                    'organisation' => $clubs[$a->scope_id]->name ?? null,
                    'url' => isset($clubs[$a->scope_id]) ? route('clubs.show', $clubs[$a->scope_id]) : null,
                    'position' => $a->position ?? 'Advisor',
                    'kind' => 'club',
                ] : [
                    'session' => $eventCommittees[$a->scope_id]->starts_at->format('M Y') ?? null,
                    'organisation' => $eventCommittees[$a->scope_id]->name ?? null,
                    'url' => isset($eventCommittees[$a->scope_id]) ? route('events.show', $eventCommittees[$a->scope_id]) : null,
                    'position' => $a->position,
                    'kind' => 'event',
                ])->values()->all() : null,
            'events' => $show('events') ? $attended->map(fn (Attendance $a) => [
                'name' => $a->event->name,
                'url' => route('events.show', $a->event),
                'club' => $a->event->club->name,
                'date' => $a->event->starts_at->format('j M Y'),
                'role' => $a->role,
                'poster_url' => $a->event->posterUrl(),
            ])->all() : null,
            'cca' => $show('cca') ? [
                'total' => round($cca->sum('hours'), 1),
                // Event CCA, or weekly-activity CCA claimed through a meeting.
                'records' => $cca->sortByDesc(fn (CcaRecord $r) => ($r->event ?? $r->meeting)?->starts_at)->map(fn (CcaRecord $r) => [
                    'event' => $r->event->name ?? ($r->meeting ? "{$r->meeting->club->name}: {$r->meeting->title}" : ''),
                    'date' => ($r->event ?? $r->meeting)?->starts_at->format('j M Y'),
                    'role' => $r->role,
                    'hours' => $r->hours,
                ])->values()->all(),
            ] : null,
        ];
    }

    private function membershipLabel(?Membership $membership): ?string
    {
        return match ($membership?->status) {
            null => null,
            MembershipStatus::Alumni => 'Alumni member',
            default => 'Member'.($membership->starts_on ? ' since '.$membership->starts_on->format('M Y') : ''),
        };
    }
}
