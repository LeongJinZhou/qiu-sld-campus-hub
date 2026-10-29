<?php

namespace App\Http\Controllers\Agm;

use App\Http\Controllers\Controller;
use App\Models\Agm;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionPosition;
use App\Models\Vote;
use App\Services\Agm\AgmPeriods;
use App\Services\Agm\Elections;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Members nominate and vote; the committee sets the windows and publishes the results. */
class ElectionController extends Controller
{
    public function __construct(private readonly Elections $elections) {}

    public function show(Request $request, Agm $agm): Response
    {
        $user = $request->user();
        $club = $agm->club;
        $isMember = $club->memberships()->where('user_id', $user->id)->whereIn('status', ['active', 'grace', 'alumni'])->exists();
        $manages = Gate::allows('viewInternal', $club);
        abort_unless($isMember || $manages, 403);

        $election = $agm->election;
        $phase = $election?->phase();
        // Tallies stay hidden until results are published (committee sees them once voting has closed).
        $showTally = $phase === 'published' || ($manages && $phase === 'closed');
        $voted = $election ? Vote::where('voter_id', $user->id)->whereIn('election_position_id', $election->positions()->select('id'))->pluck('candidate_id', 'election_position_id') : collect();

        return Inertia::render('Agm/Election', [
            'agm' => ['id' => $agm->id, 'club' => $club->only('slug', 'name'), 'starts_at' => $agm->starts_at->toIso8601String()],
            'manages' => $manages,
            'election' => $election ? [
                'id' => $election->id,
                'phase' => $phase,
                'term' => $election->termSession->name,
                'allow_alumni_vote' => $election->allow_alumni_vote,
                'nominations' => [$election->nominations_open_at->toIso8601String(), $election->nominations_close_at->toIso8601String()],
                'voting' => [$election->voting_open_at->toIso8601String(), $election->voting_close_at->toIso8601String()],
                'flags' => $manages ? $this->elections->flags($election) : [],
                'positions' => $election->positions()->with(['candidates.user.student', 'candidates' => fn ($q) => $q->withCount('votes')])->get()->map(fn (ElectionPosition $p) => [
                    'id' => $p->id, 'position' => $p->position, 'is_compulsory' => $p->is_compulsory,
                    'winner_id' => $p->winner_candidate_id, 'voted_for' => $voted[$p->id] ?? null,
                    'candidates' => $p->candidates->map(fn (Candidate $c) => [
                        'id' => $c->id, 'name' => $c->user->name, 'statement' => $c->statement,
                        'photo_url' => $c->photo_path ? Storage::disk('public')->url($c->photo_path) : $c->user->photoUrl(),
                        'programme' => $c->user->student?->programmeShortForm(), 'year_group' => $c->user->student?->year_group,
                        'votes' => $showTally ? $c->votes_count : null,
                    ]),
                ]),
            ] : null,
        ]);
    }

    public function store(Request $request, Agm $agm, AgmPeriods $periods): RedirectResponse
    {
        Gate::authorize('viewInternal', $agm->club);
        $data = $request->validate([
            'nominations_open_at' => ['required', 'date'],
            'nominations_close_at' => ['required', 'date', 'after:nominations_open_at'],
            'voting_open_at' => ['required', 'date', 'after_or_equal:nominations_close_at'],
            'voting_close_at' => ['required', 'date', 'after:voting_open_at'],
            'allow_alumni_vote' => ['boolean'],
            'extra_positions' => ['present', 'array', 'max:15'],
            'extra_positions.*' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            $periods->assertOpen($agm->period);
            $this->elections->create($agm, [...$data, 'allow_alumni_vote' => $request->boolean('allow_alumni_vote'), 'extra_positions' => array_values(array_filter($data['extra_positions']))]);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Election set up. Members can nominate themselves when nominations open.');
    }

    public function nominate(Request $request, ElectionPosition $position): RedirectResponse
    {
        $data = $request->validate(['statement' => ['required', 'string', 'max:2000'], 'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        try {
            $this->elections->nominate($position, $request->user(), $data['statement'], $request->file('photo'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "You're standing for {$position->position}. Good luck!");
    }

    public function vote(Request $request, ElectionPosition $position): RedirectResponse
    {
        $data = $request->validate(['candidate_id' => ['required', 'integer']]);
        $candidate = Candidate::findOrFail($data['candidate_id']);
        try {
            $this->elections->vote($position, $candidate, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Vote cast for {$position->position}.");
    }

    public function publish(Election $election): RedirectResponse
    {
        Gate::authorize('viewInternal', $election->agm->club);
        try {
            $this->elections->publish($election);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Results published. They now appear in the AGM Report (6.0 and 7.0).');
    }
}
