<?php

namespace App\Services\Agm;

use App\Enums\MembershipStatus;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\Agm;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\RoleAssignment;
use App\Models\Student;
use App\Models\User;
use App\Models\Vote;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use App\Services\Profiles\Certificates;
use App\Services\Roles\CompulsoryPositionsCheck;
use App\Services\Roles\RoleService;
use App\Services\SldRules;
use App\Services\Time\SessionResolver;
use App\Services\Uploads\ImageUpload;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Elections inside the AGM. The committee sets the nomination and voting times and the positions (the 4
 * compulsory ones plus any others), and names candidates (or members stand themselves). Every member has one
 * ticket per position, may change their mind up to 3 times, and cannot vote for themselves. The most votes wins.
 * Winners are named at once. After SLD acknowledges the AGM and starts the handover, the incoming committee has
 * training (view-only) access for the overlap, then the outgoing committee's roles end and the new one takes over.
 */
class Elections
{
    public const VOTE_CHANGES = 3;

    public function __construct(
        private readonly SessionResolver $sessions,
        private readonly RoleService $roles,
        private readonly AuditLogger $audit,
        private readonly Certificates $certificates,
    ) {}

    /**
     * @param  array{nominations_open_at: string, nominations_close_at: string, voting_open_at: string, voting_close_at: string, allow_alumni_vote: bool, extra_positions: list<string>}  $data
     */
    public function create(Agm $agm, array $data): Election
    {
        if ($agm->election()->exists()) {
            throw new DomainException('This AGM already has an election.');
        }
        $start = (int) substr($agm->session->name, 0, 4) + 1;
        $term = $this->sessions->forName($start.'/'.($start + 1));

        return DB::transaction(function () use ($agm, $data, $term): Election {
            $election = $agm->election()->create([
                'term_session_id' => $term->id, 'allow_alumni_vote' => $data['allow_alumni_vote'],
                ...array_intersect_key($data, array_flip(['nominations_open_at', 'nominations_close_at', 'voting_open_at', 'voting_close_at'])),
            ]);
            $extras = array_values(array_diff(array_unique(array_filter(array_map('trim', $data['extra_positions']))), CompulsoryPositionsCheck::POSITIONS));
            foreach ([...CompulsoryPositionsCheck::POSITIONS, ...$extras] as $i => $position) {
                $election->positions()->create(['position' => $position, 'is_compulsory' => $i < count(CompulsoryPositionsCheck::POSITIONS), 'sort' => $i]);
            }
            $this->audit->log('election.created', $election, ['term' => $term->name]);

            return $election;
        });
    }

    /** Before voting opens, the committee adds a position or removes one it added (the 4 compulsory stay). */
    public function addPosition(Election $election, string $name): ElectionPosition
    {
        $name = trim($name);
        if (! in_array($election->phase(), ['upcoming', 'nominating', 'between'], true)) {
            throw new DomainException('Positions are fixed once voting opens.');
        }
        if ($name === '' || $election->positions()->where('position', $name)->exists()) {
            throw new DomainException('Give the position a new name.');
        }

        return $election->positions()->create(['position' => $name, 'is_compulsory' => false, 'sort' => (int) $election->positions()->max('sort') + 1]);
    }

    public function removePosition(ElectionPosition $position): void
    {
        if ($position->is_compulsory || ! in_array($position->election->phase(), ['upcoming', 'nominating', 'between'], true)) {
            throw new DomainException($position->is_compulsory ? 'President, Vice President, Secretary and Treasurer are always elected.' : 'Positions are fixed once voting opens.');
        }
        $position->delete();
    }

    /** The committee names a candidate (picked by name or typed student ID); the same eligibility applies. */
    public function nominateFor(ElectionPosition $position, User $committee, string $studentNumber, ?string $statement): Candidate
    {
        $student = Student::where('student_number', trim($studentNumber))->first() ?? throw new DomainException('No student with that ID was found in university records.');
        $candidate = $this->nominate($position, $student->ensureUser(), $statement ?: "Nominated by the {$position->election->agm->club->name} committee.", null);
        $candidate->update(['nominated_by' => $committee->id]);

        return $candidate;
    }

    /** A student stands for one position: an active member who can serve the full July–June term. */
    public function nominate(ElectionPosition $position, User $user, string $statement, ?UploadedFile $photo): Candidate
    {
        $election = $position->election;
        if ($election->phase() !== 'nominating') {
            throw new DomainException('Nominations are not open.');
        }
        $this->assertMember($election, $user, allowAlumni: false);
        $ends = $user->student?->expected_end_date;
        if ($ends && $ends->lt($election->termSession->ends_on)) {
            throw new DomainException("{$user->name} is expected to graduate on {$ends->format('j M Y')}, before the {$election->termSession->name} term ends.");
        }
        if (Candidate::where('user_id', $user->id)->whereIn('election_position_id', $election->positions()->select('id'))->exists()) {
            throw new DomainException('You can stand for one position only.');
        }

        $candidate = $position->candidates()->create([
            'user_id' => $user->id, 'statement' => $statement,
            'photo_path' => $photo ? app(ImageUpload::class)->store($photo, "elections/{$election->id}") : null,
        ]);
        $this->audit->log('election.nominated', $candidate, ['position' => $position->position]);

        return $candidate;
    }

    public function vote(ElectionPosition $position, Candidate $candidate, User $voter): void
    {
        $election = $position->election;
        if ($election->phase() !== 'voting') {
            throw new DomainException('Voting is not open.');
        }
        if ($candidate->election_position_id !== $position->id) {
            throw new DomainException('That candidate is not standing for this position.');
        }
        $this->assertMember($election, $voter, allowAlumni: $election->allow_alumni_vote);
        if ($candidate->user_id === $voter->id) {
            throw new DomainException('You cannot vote for yourself.');
        }

        // One ticket per position; changing it is allowed up to 3 times.
        DB::transaction(function () use ($position, $candidate, $voter): void {
            $existing = $position->votes()->where('voter_id', $voter->id)->lockForUpdate()->first();
            if (! $existing) {
                try {
                    $position->votes()->create(['candidate_id' => $candidate->id, 'voter_id' => $voter->id, 'created_at' => now()]);
                } catch (UniqueConstraintViolationException) {
                    throw new DomainException("You have already voted for {$position->position}.");
                }

                return;
            }
            if ($existing->candidate_id === $candidate->id) {
                return;
            }
            if ($existing->changes >= self::VOTE_CHANGES) {
                throw new DomainException("You have already changed your {$position->position} vote ".self::VOTE_CHANGES.' times. Your vote is final.');
            }
            $existing->update(['candidate_id' => $candidate->id, 'changes' => $existing->changes + 1, 'changed_at' => now()]);
        });
    }

    /** How many more times this voter may change each vote. @return array<int, int> position id => changes left */
    public function changesLeft(Election $election, User $voter): array
    {
        return Vote::where('voter_id', $voter->id)->whereIn('election_position_id', $election->positions()->select('id'))->pluck('changes', 'election_position_id')
            ->map(fn ($c) => max(0, self::VOTE_CHANGES - (int) $c))->all();
    }

    /**
     * Issues SLD needs to see: compulsory positions without candidates, single-candidate positions, ties.
     *
     * @return list<array{position: string, issue: string}>
     */
    public function flags(Election $election): array
    {
        $flags = [];
        foreach ($election->positions()->withCount('candidates')->get() as $p) {
            if ($p->candidates_count === 0 && $p->is_compulsory) {
                $flags[] = ['position' => $p->position, 'issue' => 'no_candidate'];
            } elseif ($p->candidates_count === 1) {
                $flags[] = ['position' => $p->position, 'issue' => 'uncontested'];
            } elseif ($p->candidates_count > 1 && $election->phase() === 'closed' && $this->leaders($p)->count() > 1) {
                $flags[] = ['position' => $p->position, 'issue' => 'tie'];
            }
        }

        return $flags;
    }

    /** After voting closes: winners per position (an uncontested candidate wins), then hand over if the term has begun. */
    public function publish(Election $election): void
    {
        if ($election->phase() !== 'closed') {
            throw new DomainException('Results can be published once voting has closed.');
        }
        foreach ($this->flags($election) as $flag) {
            if ($flag['issue'] === 'no_candidate') {
                throw new DomainException("{$flag['position']} is compulsory but has no candidate.");
            }
            if ($flag['issue'] === 'tie') {
                throw new DomainException("{$flag['position']} is tied. Hold a run-off before publishing.");
            }
        }

        DB::transaction(function () use ($election): void {
            foreach ($election->positions as $position) {
                $position->update(['winner_candidate_id' => $this->leaders($position)->first()?->id]);
            }
            $election->update(['results_published_at' => now()]);
            // Winners hold their titles for the new term at once, with no access yet: SLD starts the handover after
            // acknowledging the AGM.
            foreach ($election->positions()->with('winner.user')->get() as $position) {
                if ($position->winner) {
                    $this->roles->assignClubPosition($election->agm->club, $election->termSession, $position->winner->user, $position->position)
                        ->update(['handover_state' => 'pending']);
                }
            }
            $this->audit->log('election.published', $election);
        });
        $this->tellCommittees($election->fresh(), 'Election results are out', 'The handover starts once SLD has acknowledged the AGM.');
    }

    /**
     * SLD starts the handover once the AGM is acknowledged and results are published. The incoming committee gets
     * training (view-only) access for the overlap (SLD setting, default 7 days); then the outgoing committee's roles
     * end and the incoming committee gets full access.
     */
    public function startHandover(Election $election, User $sld): void
    {
        if (! $sld->isSldAdmin()) {
            throw new DomainException('Only SLD can start the handover.');
        }
        if (! $election->agm->acknowledged_at) {
            throw new DomainException('Acknowledge the AGM first.');
        }
        if (! $election->results_published_at || $election->handover_started_at) {
            throw new DomainException($election->handover_started_at ? 'The handover has already started.' : 'Publish the election results first.');
        }
        $days = app(SldRules::class)->get('handover_days');
        DB::transaction(function () use ($election, $days): void {
            RoleAssignment::where('scope_type', ScopeType::Club)->where('scope_id', $election->agm->club_id)->where('session_id', $election->term_session_id)
                ->where('handover_state', 'pending')->update(['handover_state' => 'training', 'starts_on' => today()->toDateString()]);
            $election->update(['handover_started_at' => now(), 'handover_ends_on' => today()->addDays($days)->toDateString()]);
            $this->audit->log('election.handover_started', $election, ['overlap_days' => $days]);
        });
        $this->tellCommittees($election->fresh(), 'Handover started',
            "The incoming committee has training access (view only) for {$days} days; on ".today()->addDays($days)->format('D, j M Y').' it takes over fully.');
        if ($days === 0) {
            $this->handOver($election->fresh());
        }
    }

    /**
     * Handover day (7 days after voting closed): the outgoing committee's roles end, so they lose committee access;
     * the elected committee (already assigned, starting today) gains it.
     */
    public function handOver(Election $election): void
    {
        if ($election->handed_over_at || ! $election->handover_started_at || $election->handoverOn()?->isFuture()) {
            return;
        }
        $club = $election->agm->club;
        DB::transaction(function () use ($election, $club): void {
            RoleAssignment::where('scope_type', ScopeType::Club)->where('scope_id', $club->id)->where('session_id', $election->agm->session_id)
                ->where('role', ScopedRole::ClubCommittee)->update(['ends_on' => $election->handoverOn()->subDay()->toDateString()]);
            RoleAssignment::where('scope_type', ScopeType::Club)->where('scope_id', $club->id)->where('session_id', $election->term_session_id)
                ->where('handover_state', 'training')->update(['handover_state' => null]);
            $election->update(['handed_over_at' => now()]);
            $this->audit->log('election.handed_over', $election);
        });
        $this->tellCommittees($election, "Handover done: welcome, new {$club->name} committee", 'The new committee now has committee access. Thank you to the outgoing committee.');
        $this->thankOutgoing($election);
    }

    /** Each outgoing committee member: their committee certificate is issued, with a thank-you. */
    private function thankOutgoing(Election $election): void
    {
        $club = $election->agm->club;
        $outgoing = RoleAssignment::with('user')->where('scope_type', ScopeType::Club)->where('scope_id', $club->id)
            ->where('session_id', $election->agm->session_id)->where('role', ScopedRole::ClubCommittee)->get();
        foreach ($outgoing as $role) {
            $this->certificates->issue($role->user, 'club_committee', $role->id);
            $role->user->notify(new DocumentNotification(
                'Thank you for your service',
                "Your term as {$role->position} of {$club->name} has ended and the new committee has taken over. Your certificate is ready to download.",
                route('certificates.download', ['kind' => 'club_committee', 'source' => $role->id]),
            ));
        }
    }

    /** Daily: every handover whose overlap has ended. */
    public function handOverDue(CarbonImmutable $today): int
    {
        $due = Election::whereNotNull('handover_started_at')->whereNull('handed_over_at')->get()
            ->filter(fn (Election $e) => $e->handoverOn()?->lte($today));
        $due->each(fn (Election $e) => $this->handOver($e));

        return $due->count();
    }

    /** Outgoing and incoming committee hear about results and the handover. */
    private function tellCommittees(Election $election, string $title, string $body): void
    {
        $club = $election->agm->club;
        $people = User::whereIn('id', RoleAssignment::where('scope_type', ScopeType::Club)->where('scope_id', $club->id)
            ->whereIn('session_id', [$election->agm->session_id, $election->term_session_id])->select('user_id'))->get();
        Notification::send($people, new DocumentNotification($title, "{$club->name}: {$body}", route('election.show', $election->agm)));
    }

    /**
     * 6.0 Election and 7.0 Appointment lines for the AGM Report.
     *
     * @return array{election: list<string>, appointments: list<string>}
     */
    public function reportLines(Election $election): array
    {
        $positions = $election->positions()->with(['candidates.user', 'winner.user.student'])->withCount('votes')->get();
        $contested = $positions->filter(fn (ElectionPosition $p) => $p->candidates->isNotEmpty());
        $lines = [count($contested).' '.str('position')->plural(count($contested)).' were elected.'];
        foreach ($contested as $p) {
            $tally = $p->candidates->map(fn (Candidate $c) => "{$c->user->name} (".$p->votes()->where('candidate_id', $c->id)->count().' votes)')->join(', ');
            $lines[] = "{$p->position}: {$p->candidates->count()} ".str('candidate')->plural($p->candidates->count())." — {$tally}.";
        }
        $appointments = $positions->filter(fn (ElectionPosition $p) => $p->winner)
            ->map(fn (ElectionPosition $p) => "{$p->position}: {$p->winner->user->name}".($p->winner->user->student ? " ({$p->winner->user->student->student_number})" : ''))
            ->values()->all();

        return [
            'election' => $election->results_published_at ? $lines : ['The election results have not been published yet.'],
            'appointments' => $appointments ?: ['No appointments were made.'],
        ];
    }

    /** @return Collection<int, Candidate> candidates with the most votes */
    private function leaders(ElectionPosition $position)
    {
        $candidates = $position->candidates()->withCount('votes')->get();
        $top = $candidates->max('votes_count');

        return $candidates->where('votes_count', $top)->values();
    }

    private function assertMember(Election $election, User $user, bool $allowAlumni): void
    {
        $statuses = [MembershipStatus::Active, MembershipStatus::Grace, ...($allowAlumni ? [MembershipStatus::Alumni] : [])];
        $club = $election->agm->club;
        $member = $club->memberships()->where('user_id', $user->id)->whereIn('status', $statuses)->exists()
            || $club->roleAssignments()->where('session_id', $election->agm->session_id)->where('role', ScopedRole::ClubCommittee)->where('user_id', $user->id)->exists();
        if (! $member || (! $allowAlumni && ! $user->student?->isActive())) {
            throw new DomainException("Only {$club->name} members".($allowAlumni ? ' and alumni' : '').' can take part.');
        }
    }
}
