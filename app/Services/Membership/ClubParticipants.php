<?php

namespace App\Services\Membership;

use App\Enums\DocumentStatus;
use App\Enums\ScopeType;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\CcaRecord;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Everyone who took part in a club's events in a session — registered, attended, or both — with whether they are
 * a member, so the committee can see who joins activities but not the club, and each person's event history.
 */
class ClubParticipants
{
    /** @return array{events: int, rows: list<array<string, mixed>>} */
    public function build(Club $club, AcademicSession $session): array
    {
        $events = Event::where('club_id', $club->id)->where('session_id', $session->id)->where('status', DocumentStatus::Approved)
            ->orderBy('starts_at')->get()->keyBy('id');
        $ids = $events->keys();
        $registrations = EventRegistration::whereIn('event_id', $ids)->get()->groupBy('user_id');
        $attendance = Attendance::whereIn('event_id', $ids)->get()->groupBy('user_id');
        $cca = CcaRecord::whereIn('event_id', $ids)->get()->groupBy('user_id');
        $userIds = $registrations->keys()->merge($attendance->keys())->unique();

        $memberships = Membership::where('club_id', $club->id)->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $positions = RoleAssignment::where('scope_type', ScopeType::Club)->where('scope_id', $club->id)->where('session_id', $session->id)
            ->whereIn('user_id', $userIds)->get()->keyBy('user_id');

        $rows = User::with('student')->whereIn('id', $userIds)->get()->map(function (User $user) use ($events, $registrations, $attendance, $cca, $memberships, $positions) {
            $mine = $registrations->get($user->id, collect())->keyBy('event_id');
            $went = $attendance->get($user->id, collect())->keyBy('event_id');
            $history = $events->only($mine->keys()->merge($went->keys())->unique()->all())->sortByDesc('starts_at')
                ->map(fn (Event $e) => [
                    'name' => $e->name, 'slug' => $e->slug, 'date' => $e->starts_at->toDateString(),
                    'registered' => $mine->has($e->id),
                    'paid' => $mine->has($e->id) ? $mine[$e->id]->status === 'registered' : null,
                    'in' => $went->get($e->id)?->clock_in_at?->format('g:i A'),
                    'out' => $went->get($e->id)?->clock_out_at?->format('g:i A'),
                    'role' => $went->get($e->id)?->role,
                ])->values();

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'photo_url' => $user->photoUrl(),
                'profile_url' => route('profile.public', $user->qr_token),
                'student_number' => $user->student?->student_number,
                'programme' => $user->student?->programmeShortForm(),
                'year_group' => $user->student?->year_group,
                'member' => $this->memberStatus($memberships->get($user->id), $positions->get($user->id)),
                'registered' => $mine->count(),
                'attended' => $went->whereNotNull('clock_in_at')->count(),
                'cca_hours' => round((float) $cca->get($user->id, collect())->sum('hours'), 1),
                'last_date' => $history->max('date'),
                'events' => $history->all(),
            ];
        })->sortBy([['attended', 'desc'], ['registered', 'desc'], ['name', 'asc']])->values()->all();

        return ['events' => $events->count(), 'rows' => $rows];
    }

    /** committee · member · renewing · payment pending · alumni · former member · not a member */
    private function memberStatus(?Membership $membership, ?RoleAssignment $position): string
    {
        if ($position) {
            return 'committee';
        }

        return match ($membership?->status->value) {
            'active' => 'member',
            'grace' => 'renewing',
            'pending' => 'pending',
            'alumni' => 'alumni',
            'terminated' => 'former',
            default => 'none',
        };
    }

    /** @param list<array<string, mixed>> $rows @return Collection<int, list<mixed>> */
    public function exportRows(array $rows): Collection
    {
        return collect($rows)->map(fn (array $r) => [
            $r['name'], $r['student_number'], $r['programme'], $r['year_group'], self::LABELS[$r['member']] ?? $r['member'],
            $r['registered'], $r['attended'], $r['cca_hours'], $r['last_date'],
            collect($r['events'])->map(fn (array $e) => "{$e['date']} {$e['name']}".($e['in'] ? ' (attended)' : ' (registered)'))->implode('; '),
        ]);
    }

    public const LABELS = [
        'committee' => 'Committee', 'member' => 'Member', 'renewing' => 'Member (renewing)', 'pending' => 'Joining (payment pending)',
        'alumni' => 'Alumni member', 'former' => 'Former member', 'none' => 'Not a member',
    ];
}
