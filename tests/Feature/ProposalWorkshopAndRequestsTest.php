<?php

use App\Enums\DocumentStatus;
use App\Enums\PaymentStatus;
use App\Models\ClubFee;
use App\Models\Document;
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

    // A workshop: speakers are required…
    $submit($this->f, ['nature' => ['seminar']])->assertSessionHasErrors('speakers');
    // …and two persons in charge are enough.
    $submit($this->f, ['nature' => ['seminar'], 'speakers' => [['name' => 'Dr Aida', 'phone' => '0123456789', 'designation' => 'Engineer', 'linkedin' => '']]])
        ->assertSessionHasNoErrors()->assertRedirect(route('documents.show', $this->f['document']));
    expect($this->f['document']->fresh()->status)->toBe(DocumentStatus::InReview);
});

it('needs at least two persons in charge for a workshop', function () use ($submit) {
    $this->f['event']->committee()->where('position', '!=', 'President')->delete();

    $this->actingAs($this->f['president'])->get("/events/{$this->f['event']->slug}/proposal")
        ->assertInertia(fn (Assert $p) => $p->where('missingCommittee', ['Vice President', 'Secretary', 'Treasurer']));
    $submit($this->f, ['nature' => ['seminar'], 'speakers' => [['name' => 'Dr Aida', 'phone' => '0123456789', 'designation' => 'Engineer', 'linkedin' => '']]])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'persons in charge'));
});

it('keeps several trips, and reads an older single trip as the first', function () use ($submit) {
    $trips = [
        ['arrangement' => 'Bus', 'departure_time' => '07:30', 'departure_pickup' => 'Gate', 'dropoff_time' => '08:00', 'dropoff_point' => 'Beach', 'return_time' => '13:00', 'return_pickup' => 'Beach'],
        ['arrangement' => 'Van', 'departure_time' => '', 'departure_pickup' => '', 'dropoff_time' => '', 'dropoff_point' => '', 'return_time' => '', 'return_pickup' => ''],
    ];
    $submit($this->f, ['transport_required' => true, 'trips' => $trips])->assertSessionHasNoErrors();
    expect($this->f['document']->fresh()->data()['trips'])->toHaveCount(2);

    $legacy = ProposalForm::upgrade(['transport_arrangement' => 'Own transport', 'departure_time' => '08:00', 'departure_pickup' => 'Gate', 'return_time' => '12:00', 'return_pickup' => 'Gate']);
    expect($legacy['trips'][0])->toMatchArray(['arrangement' => 'Own transport', 'departure_time' => '08:00', 'dropoff_point' => ''])->and($legacy)->not->toHaveKey('departure_time');
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
