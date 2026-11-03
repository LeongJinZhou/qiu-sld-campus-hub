<?php

use App\Enums\DocumentStatus;
use App\Enums\PaymentStatus;
use App\Models\ClubFee;
use App\Models\Document;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Ai\ReceiptReader;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Auth\SignInService;
use App\Services\Clubs\ClubRequests;
use App\Services\Events\ProposalForm;
use App\Services\Meetings\Meetings;
use App\Services\Membership\CashHandovers;
use App\Services\Membership\MembershipService;
use App\Services\Membership\OnlinePayments;
use App\Services\Time\SessionResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->f = proposalFixture());

$submit = fn (array $f, array $overrides = []) => test()->actingAs($f['president'])
    ->post("/events/{$f['event']->slug}/proposal/submit", [...completeProposal($f, $overrides), 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()]);

it('opens a new proposal with two objective lines and accepts two written ones with a blank third', function () use ($submit) {
    expect(completeProposal($this->f, [])['objectives'])->toHaveCount(3)
        ->and(app(ProposalForm::class)->defaults($this->f['event'])['objectives'])->toBe(['', '']);

    $submit($this->f, ['objectives' => ['Clean the beach', 'Learn about marine life', '']])->assertSessionHasNoErrors()->assertRedirect();
    expect($this->f['document']->fresh()->data()['objectives'])->toBe(['Clean the beach', 'Learn about marine life']);
});

it('still needs two written objectives', function () use ($submit) {
    $submit($this->f, ['objectives' => ['Only one', '', '']])->assertSessionHasErrors('objectives');
});

it('tells the rest of the committee when someone submits the proposal', function () use ($submit) {
    Notification::fake();
    $submit($this->f)->assertRedirect();

    $others = app(Meetings::class)->committee($this->f['club'])->reject(fn (User $u) => $u->is($this->f['president']));
    Notification::assertSentTo($others, DocumentNotification::class, fn (DocumentNotification $n, array $c, User $u) => str_contains(json_encode($n->toArray($u)), 'submitted the proposal'));
});

it('asks a workshop for its speakers, and lets two persons in charge replace the four offices', function () use ($submit) {
    $event = $this->f['event'];
    $event->committee()->whereIn('position', ['Vice President', 'Secretary'])->delete();

    // Not a workshop: the four offices are compulsory.
    $submit($this->f)->assertSessionHas('error', fn (string $m) => str_contains($m, 'compulsory') || str_contains($m, 'Vice President'));

    // A workshop: speakers are required, each with a photo…
    $submit($this->f, ['nature' => ['seminar']])->assertSessionHasErrors('speakers');
    $speaker = ['name' => 'Dr Aida', 'phone' => '0123456789', 'designation' => 'Engineer', 'linkedin' => '', 'photo' => ''];
    $submit($this->f, ['nature' => ['seminar'], 'speakers' => [$speaker]])->assertSessionHasErrors('speakers.0.photo');
    // …and two persons in charge are enough.
    $submit($this->f, ['nature' => ['seminar'], 'speakers' => [[...$speaker, 'photo' => proposalPhoto($this->f)]]])
        ->assertSessionHasNoErrors()->assertRedirect(route('documents.show', $this->f['document']));
    expect($this->f['document']->fresh()->status)->toBe(DocumentStatus::InReview);
});

it('needs at least two persons in charge for a workshop', function () use ($submit) {
    $this->f['event']->committee()->where('position', '!=', 'President')->delete();

    $this->actingAs($this->f['president'])->get("/events/{$this->f['event']->slug}/proposal")
        ->assertInertia(fn (Assert $p) => $p->where('missingCommittee', ['Vice President', 'Secretary', 'Treasurer']));
    $submit($this->f, ['nature' => ['seminar'], 'speakers' => [['name' => 'Dr Aida', 'phone' => '0123456789', 'designation' => 'Engineer', 'linkedin' => '', 'photo' => proposalPhoto($this->f)]]])
        ->assertSessionHas('error', fn (?string $m) => str_contains((string) $m, 'persons in charge'));
});

it('keeps several trips with places on the way, all within the event time, and reads older trips in the new shape', function () use ($submit) {
    $day = $this->f['event']->starts_at->format('Y-m-d');
    $trip = fn (string $out, string $stop, string $back) => [
        'arrangement' => 'Bus', 'departure_at' => "{$day}T{$out}", 'departure_pickup' => 'Main gate',
        'stops' => [['name' => 'Batu Ferringhi', 'at' => "{$day}T{$stop}", 'point' => 'Car park'], ['name' => 'Beach', 'at' => "{$day}T{$stop}", 'point' => 'Lifeguard tower']],
        'return_at' => "{$day}T{$back}", 'return_pickup' => 'Beach',
    ];
    // The event runs 08:00–12:30: a 07:30 departure and a return before the drop-off are refused.
    $submit($this->f, ['transport_required' => true, 'trips' => [$trip('07:30', '09:00', '12:00'), $trip('09:00', '10:00', '09:30')]])
        ->assertSessionHasErrors(['trips.0.departure_at', 'trips.1.return_at']);
    $submit($this->f, ['transport_required' => true, 'trips' => [$trip('08:00', '09:00', '12:00'), ['arrangement' => 'Van', 'stops' => []]]])
        ->assertSessionHasErrors(['trips.1.departure_at', 'trips.1.stops']);

    $submit($this->f, ['transport_required' => true, 'trips' => [$trip('08:00', '09:00', '12:00'), $trip('08:15', '09:30', '12:30')]])->assertSessionHasNoErrors();
    expect($this->f['document']->fresh()->data()['trips'])->toHaveCount(2)->and($this->f['document']->fresh()->data()['trips'][1]['stops'])->toHaveCount(2);

    $legacy = ProposalForm::upgrade(['starts_at' => '2026-12-01T08:00', 'transport_arrangement' => 'Own transport', 'departure_time' => '08:00', 'departure_pickup' => 'Gate', 'return_time' => '12:00', 'return_pickup' => 'Gate']);
    expect($legacy['trips'][0])->toMatchArray(['arrangement' => 'Own transport', 'departure_at' => '2026-12-01T08:00', 'return_at' => '2026-12-01T12:00'])
        ->and($legacy)->not->toHaveKey('departure_time');
    $oneDrop = ProposalForm::upgrade(['starts_at' => '2026-12-01T08:00', 'trips' => [['arrangement' => 'Bus', 'departure_time' => '08:00', 'dropoff_time' => '09:00', 'dropoff_point' => 'Beach']]]);
    expect($oneDrop['trips'][0]['stops'])->toBe([['name' => 'Beach', 'at' => '2026-12-01T09:00', 'point' => 'Beach']]);
});

it('lists partner clubs or typed partners, reads an older one-line collaboration, and keeps two objective lines', function () use ($submit) {
    $submit($this->f, ['collaboration' => ['Robotics Club', 'Penang Green Society']])->assertSessionHasNoErrors();
    expect($this->f['document']->fresh()->data()['collaboration'])->toBe(['Robotics Club', 'Penang Green Society']);

    $old = ProposalForm::upgrade(['collaboration' => 'Rotaract', 'objectives' => ['Only one']]);
    expect($old['collaboration'])->toBe(['Rotaract'])->and($old['objectives'])->toBe(['Only one', '']);
    $this->actingAs($this->f['president'])->get("/events/{$this->f['event']->slug}/proposal")
        ->assertInertia(fn (Assert $p) => $p->has('clubNames')->where('data.collaboration', ['Robotics Club', 'Penang Green Society']));
});

it('uploads proposal pictures for the committee only, and needs every added picture before submitting', function () use ($submit) {
    Storage::fake('public');
    $url = "/events/{$this->f['event']->slug}/proposal/image";
    $outsider = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $this->actingAs($outsider)->postJson($url, ['image' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
    $this->actingAs($this->f['president'])->postJson($url, ['image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertUnprocessable();
    $path = $this->actingAs($this->f['president'])->postJson($url, ['image' => UploadedFile::fake()->image('a.jpg', 1600, 900)])->assertOk()->json('path');
    expect($path)->toMatch(ProposalForm::IMAGE_PATH);
    Storage::disk('public')->assertExists($path);

    $submit($this->f, ['extra_sections' => [['kind' => 'image', 'text' => 'Site map', 'rows' => [], 'image' => '']]])->assertSessionHasErrors('extra_sections.0.image');
    $submit($this->f, ['extra_sections' => [['kind' => 'image', 'text' => '', 'rows' => [], 'image' => '../../.env']]])->assertSessionHasErrors('extra_sections.0.image');
    $submit($this->f, ['extra_sections' => [['kind' => 'image', 'text' => 'Site map', 'rows' => [], 'image' => $path]]])->assertSessionHasNoErrors()->assertRedirect();
});

it('refuses added parts left empty, until they are written or deleted', function () use ($submit) {
    $blocks = [['kind' => 'heading', 'text' => '', 'rows' => []], ['kind' => 'table', 'text' => '', 'rows' => [['Risk', 'Action'], ['Rain', '']]]];
    $submit($this->f, ['extra_sections' => $blocks])->assertSessionHasErrors(['extra_sections.0.text', 'extra_sections.1.rows']);

    $blocks = [['kind' => 'heading', 'text' => 'Risks', 'rows' => []], ['kind' => 'table', 'text' => '', 'rows' => [['Risk', 'Action'], ['Rain', 'Move indoors']]]];
    $submit($this->f, ['extra_sections' => $blocks])->assertSessionHasNoErrors()->assertRedirect();
});

it('shows the guides at the first sign-in only', function () {
    $signIn = fn () => app(SignInService::class)->signIn('ahmad.razak@example.com', 'Ahmad', null, 'g1', enforceDomain: false);

    expect($signIn()->tours_seen ?? [])->toBe([])
        ->and($signIn()->fresh()->tours_seen)->toEqualCanonicalizing(['student', 'committee']);
});

it('never lets a committee member collect their own fee in cash', function () {
    $session = app(SessionResolver::class)->current();
    ClubFee::create(['club_id' => $this->f['club']->id, 'session_id' => $session->id, 'amount_sen' => 3000, 'set_by' => $this->f['president']->id, 'approved_at' => now()]);
    $treasurer = $this->f['club']->roleAssignments()->where('position', 'Treasurer')->first()->user;
    $membership = app(MembershipService::class)->join($this->f['club'], $treasurer, true);

    expect(fn () => app(CashHandovers::class)->request($membership, $treasurer))->toThrow(DomainException::class, 'your own cash')
        ->and(app(CashHandovers::class)->request($membership, $this->f['president'])->committee_user_id)->toBe($this->f['president']->id);
    $this->actingAs($treasurer)->get("/clubs/{$this->f['club']->slug}/join")
        ->assertInertia(fn (Assert $p) => $p->where('committee', fn ($list) => collect($list)->pluck('id')->doesntContain($treasurer->id)));
});

it('tells the club Treasurer when a receipt waits for a committee check', function () {
    Storage::fake('local');
    Notification::fake();
    ClubFee::create(['club_id' => $this->f['club']->id, 'session_id' => app(SessionResolver::class)->current()->id, 'amount_sen' => 3000, 'set_by' => $this->f['president']->id, 'approved_at' => now()]);
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturn(null);
    $student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $membership = app(MembershipService::class)->join($this->f['club'], $student, true);

    $payment = app(OnlinePayments::class)->submit($membership, UploadedFile::fake()->image('receipt.jpg'));

    $treasurer = $this->f['club']->roleAssignments()->where('position', 'Treasurer')->first()->user;
    expect($payment->fresh()->status)->toBe(PaymentStatus::PendingReview);
    Notification::assertSentTo($treasurer, DocumentNotification::class);
    Notification::assertNotSentTo($this->f['president'], DocumentNotification::class);
});

it('lists a club\'s requests by stage, and clears rejected ones after 30 days or on request', function () use ($submit) {
    $submit($this->f)->assertRedirect();
    $board = fn () => collect(app(ClubRequests::class)->board($this->f['club'], app(SessionResolver::class)->current()));
    expect($board()->firstWhere('id', $this->f['document']->id))->toMatchArray(['column' => 'submitted', 'title' => 'Beach Cleanup']);

    $advisorStep = app(ApprovalEngine::class)->currentStep($this->f['document']->fresh());
    app(ApprovalEngine::class)->reject($advisorStep, $this->f['advisor'], 'Not this term.');
    expect($board()->firstWhere('id', $this->f['document']->id)['column'])->toBe('rejected');

    $this->actingAs($this->f['president'])->get('/activities')
        ->assertInertia(fn (Assert $p) => $p->where('clubs.0.requests.0.column', 'rejected')->where('clubs.0.can_delete', true));

    $this->travel(31)->days();
    expect(app(ClubRequests::class)->prune())->toBe(1)->and(Document::find($this->f['document']->id))->toBeNull()->and($this->f['event']->fresh())->toBeNull();
});

it('lets the committee delete a rejected request, and nothing else', function () use ($submit) {
    $submit($this->f)->assertRedirect();
    $this->actingAs($this->f['president'])->delete("/documents/{$this->f['document']->id}/rejected")->assertSessionHas('error');

    app(ApprovalEngine::class)->reject(app(ApprovalEngine::class)->currentStep($this->f['document']->fresh()), $this->f['advisor'], 'No.');
    $this->actingAs(User::factory()->create())->delete("/documents/{$this->f['document']->id}/rejected")->assertForbidden();
    $this->actingAs($this->f['president'])->delete("/documents/{$this->f['document']->id}/rejected")->assertSessionHas('success');
    expect(Document::find($this->f['document']->id))->toBeNull();
});

it('gives SLD every proposal with PDF, Word, a list export and one ZIP per event', function () use ($submit) {
    $submit($this->f)->assertRedirect();
    $sld = sldOfficer();

    $this->actingAs($sld)->get('/admin/proposals')
        ->assertInertia(fn (Assert $p) => $p->component('Admin/Proposals/Index')->where('proposals.data.0.event', 'Beach Cleanup')->where('proposals.data.0.downloadable', true));
    $this->actingAs($sld)->get('/admin/proposals?status=approved')->assertInertia(fn (Assert $p) => $p->has('proposals.data', 0));
    $this->actingAs($sld)->get('/admin/proposals/export.xlsx')->assertOk()->assertDownload();
    $this->actingAs($sld)->get("/admin/proposals/{$this->f['document']->id}/package.zip")->assertOk()->assertDownload();
    $this->actingAs($this->f['president'])->get('/admin/proposals')->assertForbidden();
});

it('still builds the event ZIP when the server cannot make PDFs', function () use ($submit) {
    $submit($this->f)->assertRedirect();
    config(['sld.soffice' => '']);

    $this->actingAs(sldOfficer())->get("/admin/proposals/{$this->f['document']->id}/package.zip")->assertOk()->assertDownload();
});

/** A real picture uploaded through the proposal's image endpoint; returns its stored path. */
function proposalPhoto(array $f): string
{
    Storage::fake('public');

    return test()->actingAs($f['president'])->postJson("/events/{$f['event']->slug}/proposal/image", ['image' => UploadedFile::fake()->image('speaker.jpg', 400, 500)])->json('path');
}

it('shows a club\'s ongoing, upcoming and past events by year to the people who run it', function () {
    $club = $this->f['club'];
    $make = fn (string $name, $start, string $status) => Event::create([
        'club_id' => $club->id, 'session_id' => $this->f['event']->session_id, 'name' => $name, 'status' => $status,
        'starts_at' => $start, 'ends_at' => $start->copy()->addHours(3), 'expected_participants' => 10,
    ]);
    $make('Live now', now()->subHour(), 'approved');
    $make('Next month', now()->addMonth(), 'in_review');
    $make('Last year', now()->subYear()->startOfYear()->addMonths(5), 'approved');
    $make('This year', now()->subDays(2), 'approved');
    $make('Old draft', now()->subYear(), 'draft');

    $this->actingAs($this->f['president'])->get("/clubs/{$club->slug}/events")->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('Events/ClubEvents')
            ->where('ongoing.0.name', 'Live now')
            ->where('upcoming', fn ($list) => collect($list)->pluck('name')->contains('Next month') && ! collect($list)->pluck('name')->contains('Old draft'))
            ->where('years', [now()->year, now()->year - 1])
            ->where('past', fn ($list) => collect($list)->pluck('name')->all() === ['This year']));
    $this->actingAs($this->f['president'])->get("/clubs/{$club->slug}/events?year=".(now()->year - 1))
        ->assertInertia(fn (Assert $p) => $p->where('past', fn ($list) => collect($list)->pluck('name')->all() === ['Last year']));

    $outsider = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $this->actingAs($outsider)->get("/clubs/{$club->slug}/events")->assertForbidden();
});

it('keeps committee meetings to the committee and advisors, and invites the chosen people to a weekly activity with its pax and place', function () {
    Notification::fake();
    $club = $this->f['club'];
    $member = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $club->memberships()->create(['user_id' => $member->id, 'status' => 'active']);
    $at = fn (int $h) => now()->addDays(2)->setTime($h, 0)->format('Y-m-d\TH:i');
    $base = ['title' => 'Weekly session', 'starts_at' => $at(20), 'ends_at' => $at(21), 'online' => false];

    $this->actingAs($this->f['president'])->post("/clubs/{$club->slug}/meetings", [...$base, 'kind' => 'activity', 'venue' => ''])
        ->assertSessionHasErrors(['venue', 'pax', 'audience']);

    $this->actingAs($this->f['president'])->post("/clubs/{$club->slug}/meetings", [...$base, 'kind' => 'committee', 'venue' => 'Lounge', 'audience' => 'members'])->assertRedirect();
    Notification::assertNotSentTo($member, DocumentNotification::class);
    Notification::assertSentTo($this->f['advisor'], DocumentNotification::class, fn ($n, $c, User $u) => str_contains(json_encode($n->toArray($u)), 'Committee meeting'));
    expect(Meeting::latest('id')->first()->audience)->toBe('committee');

    $this->actingAs($this->f['president'])->post("/clubs/{$club->slug}/meetings", [...$base, 'kind' => 'activity', 'venue' => 'Dance studio', 'pax' => 25, 'audience' => 'members'])->assertRedirect();
    $meeting = Meeting::latest('id')->first();
    expect($meeting->only('audience', 'pax', 'venue'))->toBe(['audience' => 'members', 'pax' => 25, 'venue' => 'Dance studio']);
    Notification::assertSentTo($member, DocumentNotification::class, fn ($n, $c, User $u) => str_contains(json_encode($n->toArray($u)), 'Weekly activity') && str_contains(json_encode($n->toArray($u)), '25 pax'));
    $this->actingAs($this->f['president'])->get("/meetings/{$meeting->id}")
        ->assertInertia(fn (Assert $p) => $p->where('meeting.pax', 25)->where('people', fn ($list) => collect($list)->pluck('id')->contains($member->id)));
});
