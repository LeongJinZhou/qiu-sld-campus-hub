<?php

namespace App\Services\Agm;

use App\Enums\MembershipStatus;
use App\Enums\ScopedRole;
use App\Models\Agm;
use App\Models\Document;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Documents\Drafts;
use App\Services\Roles\CompulsoryPositionsCheck;
use App\Services\Time\SessionResolver;
use DomainException;
use Illuminate\Support\Collection;

/**
 * 01 AGM Report (CLAUDE.md §6 AGM). Editable parts (objectives, opening speech, agenda, adjournment) are the
 * form; the header, attendance, committee and election sections are computed from records and locked.
 */
class AgmReportForm
{
    public const MIN_OBJECTIVES = 2;

    public function __construct(
        private readonly Drafts $drafts,
        private readonly Elections $elections,
        private readonly SessionResolver $sessions,
    ) {}

    /** @return array<string, mixed> */
    public function defaults(Agm $agm): array
    {
        $start = $agm->starts_at;

        return [
            'objectives' => ['', '', ''],
            'speech' => [''],
            'agenda' => [
                ['start' => $start->format('g.iA'), 'end' => $start->addMinutes(10)->format('g.iA'), 'item' => 'Members arrival & taking attendance'],
            ],
            'adjournment' => '',
        ];
    }

    /** @return array<string, mixed> */
    public function current(Document $document): array
    {
        return $this->drafts->current($document, $this->defaults($document->agm));
    }

    /** @param array<string, mixed> $data */
    public function saveDraft(Document $document, array $data, int $userId): void
    {
        $this->drafts->save($document, $data, $userId);
    }

    /** @param array<string, mixed> $data */
    public function assertComplete(array $data): void
    {
        if (count(array_filter($data['objectives'] ?? [], fn ($o) => trim((string) $o) !== '')) < self::MIN_OBJECTIVES) {
            throw new DomainException('Write at least '.self::MIN_OBJECTIVES.' objectives before submitting.');
        }
    }

    /**
     * Everything taken from records: header, counts, attendance table, committee table, election and appointments.
     *
     * @return array<string, mixed>
     */
    public function computed(Agm $agm): array
    {
        $agm->loadMissing(['club', 'session', 'attendees']);
        $present = $agm->attendees->pluck('id')->all();
        $advisors = $this->assignments($agm, ScopedRole::Advisor);
        $committee = $this->ordered($this->assignments($agm, ScopedRole::ClubCommittee));
        $positions = $committee->pluck('position', 'user_id');

        // Members = active and grace memberships plus the committee; alumni are excluded from the counts.
        $members = User::with('student')->whereIn('id', $agm->club->memberships()
            ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace])->select('user_id'))
            ->get()->merge($committee->pluck('user'))->unique('id');
        $rank = array_flip(CompulsoryPositionsCheck::POSITIONS);
        $sort = fn (User $u) => [isset($positions[$u->id]) ? ($rank[$positions[$u->id]] ?? 50) : 99, $positions[$u->id] ?? '', $u->name];
        [$attended, $absent] = $members->partition(fn (User $u) => in_array($u->id, $present, true));
        $attended = $attended->sortBy($sort)->values();
        $absent = $absent->sortBy($sort)->values();

        $row = fn (User $u, string $position, string $attendance) => [
            'name' => $u->name, 'position' => $position, 'student_id' => $u->student?->student_number ?? '',
            'email' => $u->email, 'year' => str_replace('Year ', 'Y', (string) $u->student?->year_group),
            'programme' => $u->student?->programmeShortForm() ?? '', 'attendance' => $attendance,
        ];
        $election = $agm->election;

        return [
            'session' => $agm->session->name,
            'next_session' => $election?->termSession->name ?? $this->sessions->forName($this->nextName($agm->session->name))->name,
            'club' => $agm->club->name,
            'date' => $agm->starts_at->format('j F Y'),
            'time' => $agm->starts_at->format('g.i A').' – '.$agm->ends_at->format('g.i A'),
            'venue' => $agm->meet_link ? 'Google Meet' : (string) $agm->venue,
            'total_members' => $members->count(),
            'attended_members' => $attended->count(),
            'absent_members' => $absent->count(),
            'attendance' => [
                ...$advisors->map(fn (RoleAssignment $a) => [...$row($a->user, 'Advisor', 'advisor'), 'student_id' => '', 'year' => '', 'programme' => ''])->all(),
                ...$attended->map(fn (User $u) => $row($u, $positions[$u->id] ?? 'Member', 'present'))->all(),
                ...$absent->map(fn (User $u) => $row($u, $positions[$u->id] ?? 'Member', 'absent'))->all(),
            ],
            'committee' => [
                ...$advisors->map(fn (RoleAssignment $a) => ['name' => $a->user->name, 'position' => 'Advisor', 'student_id' => '', 'email' => $a->user->email, 'programme' => ''])->all(),
                ...$committee->map(fn (RoleAssignment $a) => [
                    'name' => $a->user->name, 'position' => $a->position, 'student_id' => $a->user->student?->student_number ?? '',
                    'email' => $a->user->email, 'programme' => $a->user->student?->programmeShortForm() ?? '',
                ])->values()->all(),
            ],
            'signers' => [
                'Secretary' => $committee->firstWhere('position', 'Secretary')?->user->name,
                'President' => $committee->firstWhere('position', 'President')?->user->name,
                'Advisor' => $advisors->first()?->user->name,
            ],
            'election' => $election ? $this->elections->reportLines($election) : ['election' => ['No election was held at this AGM.'], 'appointments' => ['No appointments were made.']],
        ];
    }

    private function nextName(string $session): string
    {
        $start = (int) substr($session, 0, 4) + 1;

        return $start.'/'.($start + 1);
    }

    /** @return Collection<int, RoleAssignment> */
    private function assignments(Agm $agm, ScopedRole $role): Collection
    {
        return $agm->club->roleAssignments()->where('session_id', $agm->session_id)->where('role', $role)->with('user.student')->get();
    }

    /**
     * @param  Collection<int, RoleAssignment>  $committee
     * @return Collection<int, RoleAssignment>
     */
    private function ordered(Collection $committee): Collection
    {
        $rank = array_flip(CompulsoryPositionsCheck::POSITIONS);

        return $committee->sortBy(fn (RoleAssignment $a) => [$rank[$a->position] ?? 50, $a->position, $a->user->name])->values();
    }
}
