<?php

namespace App\Services\Agm;

use App\Enums\MembershipStatus;
use App\Enums\ScopedRole;
use App\Models\Agm;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Roles\CompulsoryPositionsCheck;
use App\Services\Roles\RoleService;
use App\Services\Time\SessionResolver;
use App\Services\Uploads\ImageUpload;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Elections inside the AGM: nomination and voting windows, eligibility, one vote per
 * position per voter, flags for SLD, results, and the role handover when the new term starts (1 July).
 */
class Elections
{
    public function __construct(
        private readonly SessionResolver $sessions,
        private readonly RoleService $roles,
        private readonly AuditLogger $audit,
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

        try {
            $position->votes()->create(['candidate_id' => $candidate->id, 'voter_id' => $voter->id, 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException("You have already voted for {$position->position}.");
        }
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
            $this->audit->log('election.published', $election);
        });

        if ($election->termSession->starts_on->lte(CarbonImmutable::today())) {
            $this->handOver($election->fresh());
        }
    }

    /** Winners take their positions for the new session (scheduled on 1 July, or straight away if published later). */
    public function handOver(Election $election): void
    {
        if ($election->handed_over_at || ! $election->results_published_at) {
            return;
        }
        DB::transaction(function () use ($election): void {
            foreach ($election->positions()->with('winner.user.student')->get() as $position) {
                if ($position->winner) {
                    $this->roles->assignClubPosition($election->agm->club, $election->termSession, $position->winner->user, $position->position);
                }
            }
            $election->update(['handed_over_at' => now()]);
        });
    }

    /** Daily: every published election whose term has started. */
    public function handOverDue(CarbonImmutable $today): int
    {
        $due = Election::whereNotNull('results_published_at')->whereNull('handed_over_at')
            ->whereHas('termSession', fn ($q) => $q->whereDate('starts_on', '<=', $today))->get();
        $due->each(fn (Election $e) => $this->handOver($e));

        return $due->count();
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
