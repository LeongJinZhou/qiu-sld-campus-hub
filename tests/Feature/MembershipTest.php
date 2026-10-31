<?php

use App\Enums\HandoverStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Models\ClubFee;
use App\Models\LedgerEntry;
use App\Models\Membership;
use App\Models\Student;
use App\Models\User;
use App\Services\Ai\CashPhotoChecker;
use App\Services\Ai\ReceiptReader;
use App\Services\Membership\CashHandovers;
use App\Services\Membership\MembershipLifecycle;
use App\Services\Membership\MembershipService;
use App\Services\Membership\OnlinePayments;
use App\Services\Membership\ReceiptReading;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $this->treasurer = $this->club->roleAssignments()->where('position', 'Treasurer')->first()->user;
    $this->service = app(MembershipService::class);
});

function setFee(object $test, int $sen, bool $approved = true): void
{
    ClubFee::updateOrCreate(['club_id' => $test->club->id, 'session_id' => app(SessionResolver::class)->current()->id], [
        'amount_sen' => $sen, 'payment_url' => 'https://pay.example/gps', 'account_name' => 'Fixture Club QIU',
        'set_by' => $test->treasurer->id, 'approved_at' => $approved ? now() : null,
    ]);
}

it('waits for an SLD-approved fee before anyone can join', function () {
    setFee($this, 3000, approved: false);

    $this->service->join($this->club, $this->student, true);
})->throws(DomainException::class, 'approves');

it('activates a free club with one tap for 365 days', function () {
    setFee($this, 0);

    $m = $this->service->join($this->club, $this->student, false);

    expect($m->status)->toBe(MembershipStatus::Active)
        ->and($m->expires_on->toDateString())->toBe(today()->addDays(365)->toDateString());
});

it('requires the no-refund, no-exit acknowledgement for paid clubs', function () {
    setFee($this, 3000);

    $this->service->join($this->club, $this->student, false);
})->throws(DomainException::class, 'non-refundable');

it('only lets active camsys students join', function () {
    setFee($this, 0);
    $this->student->student->update(['status' => 'alumni']);

    $this->service->join($this->club, $this->student->fresh(), true);
})->throws(DomainException::class, 'active QIU students');

it('auto-confirms an online payment the AI reads cleanly and records ledger income', function () {
    Storage::fake('local');
    setFee($this, 3000);
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturn(new ReceiptReading(3000, today()->toDateString(), 'TXN-777', 'FIXTURE CLUB QIU', 0.97));
    $m = $this->service->join($this->club, $this->student, true);

    $payment = app(OnlinePayments::class)->submit($m, UploadedFile::fake()->image('receipt.jpg'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::AutoConfirmed)
        ->and($m->fresh()->status)->toBe(MembershipStatus::Active)
        ->and(LedgerEntry::where('club_id', $this->club->id)->sum('income_sen'))->toBe(3000);
});

it('queues unclear receipts for the Treasurer, who confirms or rejects', function () {
    Storage::fake('local');
    setFee($this, 3000);
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturn(new ReceiptReading(2000, today()->toDateString(), 'TXN-1', 'Someone', 0.9));
    $m = $this->service->join($this->club, $this->student, true);
    $payment = app(OnlinePayments::class)->submit($m, UploadedFile::fake()->image('receipt.jpg'))->fresh();

    expect($payment->status)->toBe(PaymentStatus::PendingReview)->and($payment->flags)->toContain('amount_mismatch');

    $this->service->review($payment, $this->treasurer, true, 'Paid balance in cash');
    expect($m->fresh()->status)->toBe(MembershipStatus::Active);
});

it('completes a cash handover only with the student\'s own QR and the cash confirmed, filed as membership cash', function () {
    Storage::fake('local');
    setFee($this, 3000);
    $handovers = app(CashHandovers::class);
    $m = $this->service->join($this->club, $this->student, true);

    $h = $handovers->request($m, $this->treasurer);
    $handovers->respond($h, $this->treasurer, true);
    $handovers->ready($h->fresh(), $this->student);
    expect($handovers->qrPayload($h->fresh(), $this->student))->toBeNull();
    $handovers->ready($h->fresh(), $this->treasurer);
    // The student shows their own profile QR, the same one as on their profile page.
    expect($handovers->qrPayload($h->fresh(), $this->student))->toBe(route('profile.public', $this->student->qr_token));

    // Someone else's QR, or no QR at all, is refused.
    $other = User::factory()->create();
    expect(fn () => $handovers->photo($h->fresh(), $this->treasurer, qrPhoto(route('profile.public', $other->qr_token))))->toThrow(DomainException::class, 'belongs to someone else');
    expect(fn () => $handovers->photo($h->fresh(), $this->treasurer, qrPhoto('hello')))->toThrow(DomainException::class, 'QR');

    // Without AI the member must confirm the cash before anyone can finish.
    $handovers->photo($h->fresh(), $this->treasurer, qrPhoto(route('profile.public', $this->student->qr_token)));
    expect($h->fresh()->cash_check)->toBe('needs_confirmation');
    expect(fn () => $handovers->done($h->fresh(), $this->student))->toThrow(DomainException::class, 'confirm the cash');
    $handovers->confirmCash($h->fresh(), $this->treasurer);

    $handovers->done($h->fresh(), $this->student);
    expect($h->fresh()->status)->toBe(HandoverStatus::PhotoTaken);
    $handovers->done($h->fresh(), $this->treasurer);

    $entry = LedgerEntry::where('club_id', $this->club->id)->sole();
    expect($h->fresh()->status)->toBe(HandoverStatus::Completed)
        ->and($m->fresh()->status)->toBe(MembershipStatus::Active)
        ->and($entry->description)->toBe("Membership fee (cash) – {$this->student->name}")
        ->and($entry->only('category', 'method', 'handled_by', 'income_sen'))->toBe(['category' => 'membership', 'method' => 'cash', 'handled_by' => $this->treasurer->id, 'income_sen' => 3000]);
});

it('refuses a cash photo when AI sees no cash, and skips the manual check when AI sees it', function () {
    Storage::fake('local');
    setFee($this, 3000);
    $handovers = app(CashHandovers::class);
    $h = $handovers->request($this->service->join($this->club, $this->student, true), $this->treasurer);
    $handovers->respond($h, $this->treasurer, true);
    $handovers->ready($h->fresh(), $this->student);
    $handovers->ready($h->fresh(), $this->treasurer);
    $photo = fn () => qrPhoto(route('profile.public', $this->student->qr_token));

    $this->mock(CashPhotoChecker::class)->shouldReceive('check')->andReturn(
        ['cash_visible' => false, 'qr_visible' => true, 'estimated_total_rm' => null, 'note' => 'No cash'],
        ['cash_visible' => true, 'qr_visible' => true, 'estimated_total_rm' => 30.0, 'note' => 'RM30 visible'],
    );
    $handovers = app(CashHandovers::class);
    expect(fn () => $handovers->photo($h->fresh(), $this->treasurer, $photo()))->toThrow(DomainException::class, 'No cash can be seen');
    $handovers->photo($h->fresh(), $this->treasurer, $photo());
    expect($h->fresh()->cash_check)->toBe('ai');
});

it('only lets the chosen committee member accept, and expires after 7 days', function () {
    setFee($this, 3000);
    $handovers = app(CashHandovers::class);
    $m = $this->service->join($this->club, $this->student, true);
    $h = $handovers->request($m, $this->treasurer);

    expect(fn () => $handovers->respond($h, $this->student, true))->toThrow(DomainException::class);

    $this->travel(8)->days();
    expect($handovers->expireStale())->toBe(1)->and($h->fresh()->status)->toBe(HandoverStatus::Expired);
});

it('moves members through grace, reminders and termination across 365 + 7 days', function () {
    setFee($this, 0);
    $start = CarbonImmutable::today();
    $this->service->join($this->club, $this->student, false);
    $lifecycle = app(MembershipLifecycle::class);
    $status = fn () => Membership::where('user_id', $this->student->id)->first()->status->value;

    $this->travelTo($start->addDays(364));
    $lifecycle->run(CarbonImmutable::today());
    expect($status())->toBe('active');

    $this->travelTo($start->addDays(365));
    expect($lifecycle->run(CarbonImmutable::today()))->toMatchArray(['grace' => 1, 'reminded' => 1]);

    $this->travelTo($start->addDays(368));
    expect($lifecycle->run(CarbonImmutable::today())['reminded'])->toBe(1);

    $this->travelTo($start->addDays(371));
    $lifecycle->run(CarbonImmutable::today());
    expect($status())->toBe('grace');

    $this->travelTo($start->addDays(372));
    expect($lifecycle->run(CarbonImmutable::today())['terminated'])->toBe(1)->and($status())->toBe('terminated');
});

it('lets a member in grace renew for a fresh 365 days', function () {
    setFee($this, 0);
    $start = CarbonImmutable::today();
    $this->service->join($this->club, $this->student, false);
    $this->travelTo($start->addDays(366));
    app(MembershipLifecycle::class)->run(CarbonImmutable::today());
    setFee($this, 0); // a new session started, with its own approved fee

    $m = $this->service->join($this->club, $this->student, false);

    expect($m->status)->toBe(MembershipStatus::Active)->and($m->expires_on->toDateString())->toBe(today()->addDays(365)->toDateString());
});

it('turns graduates into alumni who stay in the club', function () {
    setFee($this, 0);
    $this->service->join($this->club, $this->student, false);
    $this->student->student->update(['status' => 'alumni']);

    app(MembershipLifecycle::class)->run(CarbonImmutable::today());

    expect(Membership::where('user_id', $this->student->id)->first()->status)->toBe(MembershipStatus::Alumni);
});

it('sends both phones back to the QR step when the committee member retakes the photo', function () {
    Storage::fake('local');
    setFee($this, 3000);
    $handovers = app(CashHandovers::class);
    $h = $handovers->request($this->service->join($this->club, $this->student, true), $this->treasurer);
    $handovers->respond($h, $this->treasurer, true);
    $handovers->ready($h->fresh(), $this->student);
    $handovers->ready($h->fresh(), $this->treasurer);
    $handovers->photo($h->fresh(), $this->treasurer, qrPhoto(route('profile.public', $this->student->qr_token)));
    $path = $h->fresh()->photo_path;

    expect(fn () => $handovers->retake($h->fresh(), $this->student))->toThrow(DomainException::class);
    $handovers->retake($h->fresh(), $this->treasurer);
    expect($h->fresh()->status)->toBe(HandoverStatus::BothReady)
        ->and($h->fresh()->photo_path)->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and($handovers->qrPayload($h->fresh(), $this->student))->not->toBeNull();
});
