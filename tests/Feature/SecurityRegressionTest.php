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

/** Regression tests for issues found in an external security audit (each once reproduced a bug). */
it('does not change an approved event when a resubmission is refused', function () {
    $f = proposalFixture();
    $f['document']->update(['status' => DocumentStatus::Approved]);
    $f['event']->update(['status' => DocumentStatus::Approved]);
    $data = array_replace(app(ProposalForm::class)->defaults($f['event']), [
        'name' => 'Changed after approval', 'summary' => 'Summary', 'venue' => 'Venue', 'nature' => ['environmental'],
        'source_of_fund' => ['club'], 'introduction' => 'Intro', 'objectives' => ['One'], 'eligibility' => 'All', 'conclusion' => 'End',
        'activities' => [], 'itinerary' => [], 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
    ]);

    $this->actingAs($f['president'])->post(route('proposals.submit', $f['event']), $data)->assertSessionHas('error');

    expect($f['event']->fresh()->name)->toBe('Beach Cleanup')->and($f['document']->fresh()->status)->toBe(DocumentStatus::Approved);
});

it('records ledger income once even if two reviewers approve the same payment', function () {
    $f = proposalFixture();
    $student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    ClubFee::create(['club_id' => $f['club']->id, 'session_id' => app(SessionResolver::class)->current()->id, 'amount_sen' => 3000, 'set_by' => $f['president']->id, 'approved_at' => now()]);
    $service = app(MembershipService::class);
    $membership = $service->join($f['club'], $student, true);
    $payment = $membership->payments()->create(['club_id' => $f['club']->id, 'user_id' => $student->id, 'method' => 'online', 'amount_sen' => 3000, 'status' => PaymentStatus::PendingReview]);
    $stale = $payment->fresh();

    $service->review($payment, $f['president'], true, null);

    expect(fn () => $service->review($stale, $f['president'], true, null))->toThrow(DomainException::class)
        ->and(LedgerEntry::where('source_id', $payment->id)->count())->toBe(1);
});

it('keeps unrelated draft events from staff outside the approval chain', function () {
    $f = proposalFixture();
    $f['advisor']->staff->update(['active' => false]);
    $other = Club::create(['name' => 'Unrelated Club']);
    $event = Event::create(['club_id' => $other->id, 'session_id' => $f['event']->session_id, 'name' => 'Confidential draft',
        'starts_at' => now()->addMonths(4), 'ends_at' => now()->addMonths(4)->addHour(), 'expected_participants' => 20]);

    $this->actingAs($f['advisor'])->get(route('events.show', $event))->assertForbidden();
});

it('makes events from past sessions read-only for the club', function () {
    $f = proposalFixture();
    $this->travel(2)->years();

    expect($f['president']->can('update', $f['event']))->toBeFalse()
        ->and(sldOfficer()->can('update', $f['event']))->toBeTrue();
});

it('hides the counts of hidden profile sections', function () {
    $f = proposalFixture();
    $f['president']->update(['hidden_sections' => ['clubs', 'positions', 'events', 'cca']]);

    $public = app(PublicProfile::class)->for($f['president']);

    expect($public['clubs'])->toBeNull()->and($public['stats'])->toBe(['clubs' => null, 'positions' => null, 'events' => null, 'cca_hours' => null]);
});
