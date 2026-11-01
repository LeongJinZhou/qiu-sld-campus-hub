<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentStatus;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Exceptions\NotInRecordsException;
use App\Models\Club;
use App\Models\Document;
use App\Models\LedgerEntry;
use App\Models\RoleAssignment;
use App\Models\Signature;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Approvals\SignatureStore;
use App\Services\Auth\SignInService;
use App\Services\Events\AttendanceQr;
use App\Services\Events\PostMortemForm;
use App\Services\Finance\FundWithdrawals;
use App\Services\Finance\WithdrawalProgress;
use App\Services\Membership\ReceiptReading;
use App\Services\Membership\ReceiptVerifier;
use App\Services\Roles\RoleService;
use App\Services\Roles\StaffAccounts;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00:00', config('app.timezone')));
    $this->f = proposalFixture();
    $this->student = otherStudent();
});

// These assertions express the required boundary, not the current implementation.
// A failing case is retained as reproducible evidence; do not weaken it to match a bug.
it('blocks an unregistered student at a paid event even with a current QR', function () {
    $event = $this->f['event'];
    $event->update(['status' => DocumentStatus::Approved, 'attendance_open' => true,
        'fee_sen' => 5000, 'starts_at' => now(), 'ends_at' => now()->addHours(2)]);
    $code = app(AttendanceQr::class)->code($event, 'in');
    expect(app(AttendanceQr::class)->isValid($event, 'in', $code))->toBeTrue();

    $this->actingAs($this->student)->get(route('events.attend', [$event, 'action' => 'in', 'code' => $code]));

    $this->assertDatabaseMissing('attendance', ['event_id' => $event->id, 'user_id' => $this->student->id]);
})->group('hard-audit');

it('blocks a non-member at a members-only event even with a current QR', function () {
    $event = $this->f['event'];
    $event->update(['status' => DocumentStatus::Approved, 'attendance_open' => true,
        'members_only' => true, 'starts_at' => now(), 'ends_at' => now()->addHours(2)]);
    $this->actingAs($this->student)->get(route('events.attend', [$event, 'action' => 'in', 'code' => app(AttendanceQr::class)->code($event, 'in')]));

    $this->assertDatabaseMissing('attendance', ['event_id' => $event->id, 'user_id' => $this->student->id]);
})->group('hard-audit');

it('requires manual review when an otherwise matching payment has no payer name', function () {
    $reading = new ReceiptReading(3000, today()->toDateString(), 'NEW-REF', 'Fixture Club', 0.99, null);
    $decision = app(ReceiptVerifier::class)->assess($reading, 3000, 'Fixture Club', [], CarbonImmutable::today(), $this->student->name);

    expect($decision['status'])->toBe(PaymentStatus::PendingReview);
})->group('hard-audit');

it('rejects repeated budget lines whose combined withdrawal exceeds the approved line', function () {
    approvedEventWithBudget($this);
    $item = ['description' => 'Gloves', 'unit_sen' => 500, 'quantity' => 40, 'method' => 'vendor'];

    expect(fn () => app(FundWithdrawals::class)->normalise($this->f['club'], [
        'scope' => 'event', 'event_id' => $this->f['event']->id, 'items' => [$item, $item],
        'bank' => ['bank_name' => 'Maybank', 'account_name' => 'Audit vendor', 'account_number' => '1234567890'],
    ]))->toThrow(DomainException::class);
})->group('hard-audit');

it('posts a withdrawal only once when two callers hold stale copies', function () {
    $d = Document::create(['type' => DocumentType::FundWithdrawal, 'club_id' => $this->f['club']->id,
        'session_id' => $this->f['event']->session_id, 'created_by' => $this->f['president']->id,
        'status' => DocumentStatus::Approved, 'finance_stage' => 'bankin_uploaded', 'bankin_path' => 'audit/proof.jpg']);
    $d->versions()->create(['number' => 1, 'data' => ['total_sen' => 5000, 'purpose' => 'Audit advance'], 'created_by' => $this->f['president']->id]);
    Storage::disk('local')->put('audit/proof.jpg', 'test evidence');
    $stale = $d->fresh();
    $service = app(WithdrawalProgress::class);
    $admin = sldOfficer();
    $service->complete($d, $admin);
    try {
        $service->complete($stale, $admin);
    } catch (DomainException) {
        // A stale caller must be refused or return the existing entry.
    }

    expect(LedgerEntry::where('source_type', $d->getMorphClass())->where('source_id', $d->id)->count())->toBe(1);
})->group('hard-audit');

it('removes financial powers after a committee assignment has ended', function () {
    $president = $this->f['president'];
    $this->f['club']->roleAssignments()->where('user_id', $president->id)->update(['ends_on' => today()->subDay()]);

    expect($president->can('viewInternal', $this->f['club']))->toBeFalse()
        ->and($president->can('treasure', $this->f['club']))->toBeFalse();
})->group('hard-audit');

it('grants financial powers to an incoming Treasurer whose handover has activated', function () {
    $current = app(SessionResolver::class)->current();
    $start = (int) substr($current->name, 0, 4) + 1;
    $next = app(SessionResolver::class)->forName($start.'/'.($start + 1));
    RoleAssignment::create(['user_id' => $this->student->id, 'role' => ScopedRole::ClubCommittee,
        'scope_type' => ScopeType::Club, 'scope_id' => $this->f['club']->id, 'session_id' => $next->id,
        'position' => 'Treasurer', 'starts_on' => today(), 'ends_on' => $next->ends_on]);

    expect($this->student->can('viewInternal', $this->f['club']))->toBeTrue()
        ->and($this->student->can('treasure', $this->f['club']))->toBeTrue();
})->group('hard-audit');

it('denies assigned-club data after the advisor staff account is disabled', function () {
    $advisor = $this->f['advisor'];
    app(StaffAccounts::class)->update($advisor->staff, ['name' => $advisor->name, 'active' => false]);

    $this->actingAs($advisor)->getJson('/clubs/'.$this->f['club']->slug.'/finance')->assertForbidden();
})->group('hard-audit');

it('refuses personal-email login for a privileged non-alumni account', function () {
    $admin = superAdmin();
    $admin->update(['personal_email' => 'audit-linked@example.com']);

    expect(fn () => app(SignInService::class)->signInWithPersonalEmail('audit-linked@example.com'))
        ->toThrow(NotInRecordsException::class);
})->group('hard-audit');

it('does not allow two accounts to link the same personal login email', function () {
    $this->f['president']->update(['personal_email' => 'audit-linked@example.com']);
    $this->actingAs($this->student)->post('/me', ['personal_email' => 'AUDIT-LINKED@example.com', 'hidden_sections' => []])
        ->assertSessionHasErrors('personal_email');
})->group('hard-audit');

it('rejects a blank signature image with stationary synthetic strokes', function () {
    $strokes = json_encode(['w' => 400, 'h' => 180, 'device' => 'touch',
        'strokes' => [array_fill(0, 25, [10, 10, 0, 0])]], JSON_THROW_ON_ERROR);

    expect(fn () => app(SignatureStore::class)->store($this->student, signaturePng(), 'Student', [], $strokes))
        ->toThrow(DomainException::class);
})->group('hard-audit');

it('preserves the earlier signed version when a rejection is corrected and resubmitted', function () {
    $f = $this->f;
    $engine = app(ApprovalEngine::class);
    $data = completeProposal($f);
    $engine->submit($f['document'], $f['president'], $data, signaturePng(), signatureStrokes());
    $old = Signature::where('document_id', $f['document']->id)->sole();
    $engine->reject($engine->currentStep($f['document']->fresh()), $f['advisor'], 'Correct the introduction');
    $engine->submit($f['document']->fresh(), $f['president'], [...$data, 'introduction' => 'Corrected text'], signaturePng(), signatureStrokes());

    expect(Signature::find($old->id))->not->toBeNull();
})->group('hard-audit');

it('does not let a stale approval be signed again after the workflow advances', function () {
    $f = $this->f;
    $engine = app(ApprovalEngine::class);
    $engine->submit($f['document'], $f['president'], completeProposal($f), signaturePng());
    $step = $engine->currentStep($f['document']->fresh());
    $stale = $step->fresh();
    $engine->approve($step, $f['advisor'], signaturePng());

    expect(fn () => $engine->approve($stale, $f['advisor'], signaturePng()))->toThrow(DomainException::class);
})->group('hard-audit');

it('does not accept a different club committee signature for this proposal', function () {
    $f = $this->f;
    $other = Club::create(['name' => 'Other audit club']);
    app(RoleService::class)->assignClubPosition($other, app(SessionResolver::class)->current(), $this->student, 'President');

    expect(fn () => app(ApprovalEngine::class)->submit($f['document'], $this->student, completeProposal($f), signaturePng()))
        ->toThrow(DomainException::class);
    expect(Signature::count())->toBe(0)->and($f['document']->versions()->count())->toBe(0);
})->group('hard-audit');

it('rejects a QR code from another event and rejects an expired code', function () {
    $qr = app(AttendanceQr::class);
    $event = $this->f['event'];
    $other = $event->replicate();
    $other->slug = 'other-audit-event';
    $other->save();
    $code = $qr->code($event, 'in');

    expect($qr->isValid($other, 'in', $code))->toBeFalse()
        ->and($qr->isValid($event, 'out', $code))->toBeFalse();
    $this->travel(3)->minutes();
    expect($qr->isValid($event, 'in', $code))->toBeFalse();
})->group('hard-audit');

it('records the server identity instead of browser supplied signer fields', function () {
    $f = $this->f;
    $this->actingAs($f['president'])->post('/events/'.$f['event']->slug.'/proposal/submit', [
        ...completeProposal($f), 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(),
        'signer_name' => 'Forged COO', 'position_at_time' => 'COO', 'signed_at' => '2000-01-01',
    ])->assertSessionHasNoErrors();

    $s = Signature::where('document_id', $f['document']->id)->sole();
    expect($s->signer_name)->toBe($f['president']->name)
        ->and($s->position_at_time)->toBe('President')->and($s->signed_at->isToday())->toBeTrue();
})->group('hard-audit');

it('does not accept a non-SLD user completing a withdrawal', function () {
    $d = Document::create(['type' => DocumentType::FundWithdrawal, 'club_id' => $this->f['club']->id,
        'session_id' => $this->f['event']->session_id, 'created_by' => $this->f['president']->id,
        'status' => DocumentStatus::Approved, 'finance_stage' => 'bankin_uploaded']);

    expect(fn () => app(WithdrawalProgress::class)->complete($d, $this->f['president']))->toThrow(DomainException::class);
    expect(LedgerEntry::count())->toBe(0);
})->group('hard-audit');

it('blocks stale workflow stages from skipping Finance approval', function () {
    $d = Document::create(['type' => DocumentType::FundWithdrawal, 'club_id' => $this->f['club']->id,
        'session_id' => $this->f['event']->session_id, 'created_by' => $this->f['president']->id,
        'status' => DocumentStatus::Approved, 'finance_stage' => 'awaiting_finance']);

    expect(fn () => app(WithdrawalProgress::class)->complete($d, sldOfficer()))->toThrow(DomainException::class);
    expect(LedgerEntry::count())->toBe(0);
})->group('hard-audit');

it('returns 429 after the public signature verification limit is exceeded', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->get('/verify/AUDIT-NOT-FOUND')->assertOk();
    }
    $this->get('/verify/AUDIT-NOT-FOUND')->assertStatus(429);
})->group('hard-audit');

it('does not exhaust an AI action limit by making directory lookups', function () {
    $this->actingAs($this->f['president']);
    for ($i = 0; $i < 6; $i++) {
        $this->getJson('/people/lookup?q=Ah')->assertOk();
    }
    Queue::fake();
    $response = $this->post('/events/'.$this->f['event']->slug.'/post-mortem/suggest', [
        'rows' => app(PostMortemForm::class)->defaults()['rows'], 'recommendations' => [],
    ]);

    expect($response->getStatusCode())->not->toBe(429);
})->group('hard-audit');
