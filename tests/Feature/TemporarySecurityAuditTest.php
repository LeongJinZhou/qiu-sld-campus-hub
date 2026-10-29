<?php

use App\Enums\DocumentStatus;
use App\Enums\PaymentStatus;
use App\Models\Club;
use App\Models\ClubFee;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\Student;
use App\Services\Events\ProposalForm;
use App\Services\Membership\MembershipService;
use App\Services\Profiles\PublicProfile;
use App\Services\Time\SessionResolver;

it('audit reproduces approved event mutation through refused resubmission', function () {
    $f = proposalFixture();
    $f['document']->update(['status' => DocumentStatus::Approved]);
    $f['event']->update(['status' => DocumentStatus::Approved]);
    $data = app(ProposalForm::class)->defaults($f['event']);
    $data = array_replace($data, [
        'name' => 'Changed after approval', 'summary' => 'Summary', 'venue' => 'Venue',
        'nature' => ['environmental'], 'source_of_fund' => ['club'],
        'introduction' => 'Intro', 'objectives' => ['One'], 'eligibility' => 'All', 'conclusion' => 'End',
        'activities' => [], 'itinerary' => [], 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ]);
    $this->actingAs($f['president'])->post(route('proposals.submit', $f['event']), $data)
        ->assertSessionHas('error');
    expect($f['event']->fresh()->name)->toBe('Changed after approval')
        ->and($f['document']->fresh()->status)->toBe(DocumentStatus::Approved);
});

it('audit reproduces duplicate ledger activation using two stale review instances', function () {
    $f = proposalFixture();
    $student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    ClubFee::create(['club_id' => $f['club']->id, 'session_id' => app(SessionResolver::class)->current()->id,
        'amount_sen' => 3000, 'set_by' => $f['president']->id, 'approved_at' => now()]);
    $service = app(MembershipService::class);
    $membership = $service->join($f['club'], $student, true);
    $payment = $membership->payments()->create(['club_id' => $f['club']->id, 'user_id' => $student->id,
        'method' => 'online', 'amount_sen' => 3000, 'status' => PaymentStatus::PendingReview]);
    $stale = $payment->fresh();
    $service->review($payment, $f['president'], true, null);
    $service->review($stale, $f['president'], true, null);
    expect(LedgerEntry::where('source_id', $payment->id)->count())->toBe(2);
});

it('audit reproduces inactive staff access to an unrelated draft event', function () {
    $f = proposalFixture();
    $staff = $f['advisor'];
    $staff->staff->update(['active' => false]);
    $other = Club::create(['name' => 'Unrelated Club']);
    $event = Event::create(['club_id' => $other->id, 'session_id' => $f['event']->session_id,
        'name' => 'Confidential draft', 'starts_at' => now()->addMonths(4), 'ends_at' => now()->addMonths(4)->addHour(),
        'expected_participants' => 20]);
    $this->actingAs($staff)->get(route('events.show', $event))->assertOk();
});

it('audit reproduces mutation permission on past session events', function () {
    $f = proposalFixture();
    $this->travel(2)->years();
    expect($f['president']->can('update', $f['event']))->toBeTrue();
});

it('audit reproduces hidden profile statistics remaining public', function () {
    $f = proposalFixture();
    $user = $f['president'];
    $user->update(['hidden_sections' => ['clubs', 'positions', 'events', 'cca']]);
    $public = app(PublicProfile::class)->for($user);
    expect($public['clubs'])->toBeNull()->and($public['stats']['clubs'])->toBeGreaterThan(0);
});
