<?php

use App\Enums\DocumentStatus;
use App\Models\Election;
use App\Models\GeneratedFile;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Models\Vote;
use App\Services\Agm\AgmReportForm;
use App\Services\Agm\Elections;
use App\Services\Reports\AnnualReports;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Notification::fake();
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->secretary = $this->club->roleAssignments()->where('position', 'Secretary')->first()->user;
    $this->sld = sldOfficer();
    $this->agm = openPeriodAndAgm($this);
});

function makeElection(object $t, array $extra = [], bool $alumni = false): Election
{
    $t->actingAs($t->secretary)->post("/agms/{$t->agm->id}/election", [
        'nominations_open_at' => now()->subHour()->toDateTimeString(), 'nominations_close_at' => now()->addDay()->toDateTimeString(),
        'voting_open_at' => now()->addDays(2)->toDateTimeString(), 'voting_close_at' => now()->addDays(3)->toDateTimeString(),
        'allow_alumni_vote' => $alumni, 'extra_positions' => $extra,
    ])->assertSessionHas('success');

    return Election::sole();
}

function positionNamed(Election $e, string $name)
{
    return $e->positions()->where('position', $name)->firstOrFail();
}

it('puts the four compulsory positions on the ballot and checks eligibility and the full term', function () {
    $election = makeElection($this, ['Publicity Director']);
    expect($election->positions()->pluck('position')->all())->toBe(['President', 'Vice President', 'Secretary', 'Treasurer', 'Publicity Director'])
        ->and($election->termSession->name)->toBe(substr($this->agm->session->name, 5).'/'.((int) substr($this->agm->session->name, 5) + 1));
    [$member, $other] = plainMembers($this, 2);
    $president = positionNamed($election, 'President');

    $this->actingAs(User::factory()->create())->post("/election-positions/{$president->id}/nominate", ['statement' => 'Vote me'])->assertSessionHas('error');
    $member->student->update(['expected_end_date' => $election->termSession->starts_on->addMonths(3)]);
    $this->actingAs($member)->post("/election-positions/{$president->id}/nominate", ['statement' => 'Vote me'])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'expected to graduate'));
    $member->student->update(['expected_end_date' => $election->termSession->ends_on->addYear()]);
    $this->actingAs($member)->post("/election-positions/{$president->id}/nominate", ['statement' => 'Vote me'])->assertSessionHas('success');
    $this->actingAs($member)->post('/election-positions/'.positionNamed($election, 'Treasurer')->id.'/nominate', ['statement' => 'Again'])
        ->assertSessionHas('error', 'You can stand for one position only.');

    expect(collect(app(Elections::class)->flags($election))->pluck('issue', 'position')->all())
        ->toMatchArray(['President' => 'uncontested', 'Vice President' => 'no_candidate', 'Secretary' => 'no_candidate', 'Treasurer' => 'no_candidate']);
});

it('allows one vote per position, alumni only when switched on, and hands roles over when the term starts', function () {
    $election = makeElection($this);
    $members = plainMembers($this, 5);
    $alumnus = User::factory()->create();
    $this->club->memberships()->create(['user_id' => $alumnus->id, 'status' => 'alumni']);
    foreach ($members as $m) {
        $m->student->update(['expected_end_date' => now()->addYears(3)]);
    }
    $service = app(Elections::class);
    foreach (['President', 'Vice President', 'Secretary', 'Treasurer'] as $i => $name) {
        $service->nominate(positionNamed($election, $name), $members[$i], "I will serve as {$name}", null);
    }
    $rival = $service->nominate(positionNamed($election, 'President'), $members[4], 'Me too', null);
    $president = positionNamed($election, 'President');
    $leader = $president->candidates()->where('user_id', $members[0]->id)->first();

    $this->actingAs($members[3])->post("/election-positions/{$president->id}/vote", ['candidate_id' => $leader->id])->assertSessionHas('error', 'Voting is not open.');
    $this->travelTo($election->voting_open_at->addMinute());
    $this->actingAs($members[3])->post("/election-positions/{$president->id}/vote", ['candidate_id' => $leader->id])->assertSessionHas('success');
    $this->actingAs($members[3])->post("/election-positions/{$president->id}/vote", ['candidate_id' => $rival->id])->assertSessionHas('error', 'You have already voted for President.');
    $this->actingAs($members[1])->post("/election-positions/{$president->id}/vote", ['candidate_id' => $leader->id])->assertSessionHas('success');
    $this->actingAs($members[2])->post("/election-positions/{$president->id}/vote", ['candidate_id' => $rival->id])->assertSessionHas('success');
    $this->actingAs($alumnus)->post("/election-positions/{$president->id}/vote", ['candidate_id' => $rival->id])->assertSessionHas('error');
    expect(Vote::count())->toBe(3);
    // Tallies are hidden from members while voting is open.
    $this->actingAs($members[3])->get("/agms/{$this->agm->id}/election")->assertOk()
        ->assertInertia(fn ($page) => $page->where('election.positions.0.candidates.0.votes', null));

    $this->actingAs($this->secretary)->post("/elections/{$election->id}/publish")->assertSessionHas('error', 'Results can be published once voting has closed.');
    $this->travelTo($election->voting_close_at->addMinute());
    $this->actingAs($this->secretary)->post("/elections/{$election->id}/publish")->assertSessionHas('success');
    expect(positionNamed($election, 'President')->winner->user_id)->toBe($members[0]->id);

    $lines = app(AgmReportForm::class)->computed($this->agm->fresh())['election'];
    expect($lines['election'][0])->toBe('4 positions were elected.')
        ->and($lines['election'][1])->toContain('President: 2 candidates')->toContain('(2 votes)')
        ->and($lines['appointments'][0])->toBe("President: {$members[0]->name} ({$members[0]->student->student_number})");

    // Handover on 1 July, when the new term begins.
    $term = $election->fresh()->termSession;
    expect($election->fresh()->handed_over_at)->toBeNull();
    $this->travelTo($term->starts_on->setTime(0, 30));
    $this->artisan('schedule:test', ['--name' => 'election-handover'])->assertSuccessful();
    expect(RoleAssignment::where('session_id', $term->id)->where('scope_id', $this->club->id)->where('position', 'President')->value('user_id'))->toBe($members[0]->id)
        ->and($election->fresh()->handed_over_at)->not->toBeNull();
});

it('blocks publishing while a compulsory position has no candidate', function () {
    $election = makeElection($this);
    $this->travelTo($election->voting_close_at->addMinute());
    $this->actingAs($this->secretary)->post("/elections/{$election->id}/publish")->assertSessionHas('error', 'President is compulsory but has no candidate.');
});

it('builds the Annual Activities Report from the official PPTX with one details and one photos slide per event', function () {
    $this->f['event']->update(['status' => DocumentStatus::Approved, 'venue' => 'Pantai Remis']);
    $reports = app(AnnualReports::class);
    $file = $reports->for($this->club, $this->agm->session, $this->secretary);
    $this->actingAs($this->secretary)->put("/clubs/{$this->club->slug}/annual-report", ['details' => [$this->f['event']->id => 'Members cleaned the beach.'], 'theme_id' => null])->assertSessionHas('success');

    $this->actingAs($this->secretary)->post("/clubs/{$this->club->slug}/annual-report/generate")->assertSessionHas('success');
    $file->refresh();
    expect($file->pptx_path)->toBeFile()->and($file->pdf_path)->toBeFile();
    $zip = new ZipArchive;
    $zip->open($file->pptx_path);
    $slides = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->filter(fn ($n) => preg_match('#^ppt/slides/slide\d+\.xml$#', $n));
    $text = $slides->map(fn ($n) => strip_tags((string) $zip->getFromName($n)))->join(' ');
    expect($slides)->toHaveCount(4)
        ->and($text)->toContain('Fixture Club')->toContain('Event Name: Beach Cleanup')->toContain('Members cleaned the beach.')->toContain('Venue: Pantai Remis')
        ->not->toContain('Majlis Peluncuran');
    $this->actingAs($this->secretary)->get("/clubs/{$this->club->slug}/annual-report/pptx")->assertOk();
})->skip(fn () => ! is_executable((string) config('sld.soffice')) || (new Process([config('sld.python'), '-c', 'import pptx']))->run() !== 0, 'python-pptx or LibreOffice not installed');

it('submits the AGM package only when all four parts are ready', function () {
    $this->actingAs($this->secretary)->post("/agms/{$this->agm->id}/package")->assertSessionHas('error', fn ($m) => str_contains($m, 'AGM Report') && str_contains($m, 'Annual Activities Report'));

    $this->agm->report->update(['status' => DocumentStatus::InReview]);
    $this->club->openingBalance()->create(['amount_sen' => 10000, 'as_of' => today(), 'set_by' => $this->sld->id]);
    GeneratedFile::create(['club_id' => $this->club->id, 'session_id' => $this->agm->session_id, 'kind' => GeneratedFile::ANNUAL_REPORT, 'generated_at' => now(), 'created_by' => $this->secretary->id]);

    $this->actingAs($this->secretary)->post("/agms/{$this->agm->id}/package")->assertSessionHas('success');
    expect($this->agm->fresh()->package_submitted_at)->not->toBeNull();
    $this->actingAs($this->sld)->get('/admin/agm')->assertOk()->assertInertia(fn ($page) => $page->where('clubs.0.agm.package_submitted_at', fn ($v) => $v !== null));
});
