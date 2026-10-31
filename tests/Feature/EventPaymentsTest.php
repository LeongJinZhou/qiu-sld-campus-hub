<?php

use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\MembershipDrive;
use App\Models\Student;
use App\Services\Ai\ReceiptReader;
use App\Services\Events\AttendanceService;
use App\Services\Events\EventPayments;
use App\Services\Events\EventRegistrations;
use App\Services\Finance\PaymentBreakdown;
use App\Services\Membership\CashHandovers;
use App\Services\Membership\MembershipService;
use App\Services\Membership\ReceiptReading;
use App\Services\Notifications\ActionItems;
use App\Services\Time\SessionResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->event = $this->f['event'];
    $this->event->update(['registration_open' => true]);
    $this->student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    $this->treasurer = $this->club->roleAssignments()->where('position', 'Treasurer')->first()->user;
    app(EventPayments::class)->configure($this->event, $this->treasurer, ['fee_rm' => 15, 'payment_url' => 'https://pay.example/beach', 'payment_account_name' => 'Fixture Club QIU']);
});

it('reserves a place as payment pending and lists it under Action needed', function () {
    $registration = app(EventRegistrations::class)->register($this->event->fresh(), $this->student);

    expect($registration->status)->toBe('pending_payment')
        ->and(collect(app(ActionItems::class)->for($this->student))->pluck('key'))->toContain("event-payment:{$registration->id}");
});

it('confirms an online event payment, files it under that event with an order ID', function () {
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturn(new ReceiptReading(1500, today()->toDateString(), 'TXN-EV1', 'FIXTURE CLUB QIU', 0.97));
    $registration = app(EventRegistrations::class)->register($this->event->fresh(), $this->student);

    $payment = app(EventPayments::class)->submitOnline($registration, UploadedFile::fake()->image('receipt.jpg'))->fresh();

    $entry = LedgerEntry::where('club_id', $this->club->id)->sole();
    expect($payment->status)->toBe(PaymentStatus::AutoConfirmed)
        ->and($payment->order_id)->toBe('FIXTURE-CLUB_BEACH-CLEANUP_0001')
        ->and($registration->fresh()->status)->toBe('registered')
        ->and($entry->only('category', 'event_id', 'income_sen', 'order_id'))->toBe(['category' => 'event', 'event_id' => $this->event->id, 'income_sen' => 1500, 'order_id' => 'FIXTURE-CLUB_BEACH-CLEANUP_0001']);
});

it('takes event cash through the QR handover and keeps two events apart', function () {
    $other = Event::create(['club_id' => $this->club->id, 'session_id' => $this->event->session_id, 'name' => 'Movie Night', 'starts_at' => now()->addMonths(4), 'ends_at' => now()->addMonths(4)->addHours(3), 'registration_open' => true]);
    app(EventPayments::class)->configure($other, $this->treasurer, ['fee_rm' => 5, 'payment_url' => null, 'payment_account_name' => null]);
    $handovers = app(CashHandovers::class);

    foreach ([$this->event, $other] as $event) {
        $registration = app(EventRegistrations::class)->register($event->fresh(), $this->student);
        $h = $handovers->requestForEvent($registration, $this->treasurer);
        $handovers->respond($h, $this->treasurer, true);
        $handovers->ready($h->fresh(), $this->student);
        $handovers->ready($h->fresh(), $this->treasurer);
        $handovers->photo($h->fresh(), $this->treasurer, qrPhoto(route('profile.public', $this->student->qr_token)));
        $handovers->confirmCash($h->fresh(), $this->treasurer);
        $handovers->done($h->fresh(), $this->student);
        $handovers->done($h->fresh(), $this->treasurer);
        expect($registration->fresh()->status)->toBe('registered');
    }

    expect(LedgerEntry::where('event_id', $this->event->id)->pluck('income_sen')->all())->toBe([1500])
        ->and(LedgerEntry::where('event_id', $other->id)->pluck('order_id')->all())->toBe(['FIXTURE-CLUB_MOVIE-NIGHT_0001'])
        ->and(LedgerEntry::where('event_id', $other->id)->value('handled_by'))->toBe($this->treasurer->id);
});

it('gives membership payments their own running order number', function () {
    $m = $this->club->memberships()->create(['user_id' => $this->student->id, 'status' => 'pending']);
    $payment = $m->payments()->create(['club_id' => $this->club->id, 'user_id' => $this->student->id, 'method' => 'cash', 'amount_sen' => 3000, 'status' => PaymentStatus::CashPending]);

    expect($payment->order_id)->toBe('FIXTURE-CLUB_MEMBERSHIP_0001');
});

it('allows at most two uploaded payment QR codes per event', function () {
    $qr = fn () => UploadedFile::fake()->image('qr.png', 300, 300);

    app(EventPayments::class)->configure($this->event->fresh(), $this->treasurer, ['fee_rm' => 15, 'payment_url' => null, 'payment_account_name' => 'Fixture Club QIU'], [$qr(), $qr(), $qr()]);
})->throws(DomainException::class, 'at most 2');

it('blocks clock-in for a participant who has not paid', function () {
    $this->event->update(['attendance_open' => true, 'starts_at' => now(), 'ends_at' => now()->addHours(2)]);
    app(EventRegistrations::class)->register($this->event->fresh(), $this->student);

    app(AttendanceService::class)->clock($this->event->fresh(), $this->student, 'in', 'qr');
})->throws(DomainException::class, 'not paid');

it('tallies who registered and paid for an event, matched against the ledger, and exports it', function () {
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturn(new ReceiptReading(1500, today()->toDateString(), 'TXN-EV2', 'FIXTURE CLUB QIU', 0.97));
    $registration = app(EventRegistrations::class)->register($this->event->fresh(), $this->student);
    app(EventPayments::class)->submitOnline($registration, UploadedFile::fake()->image('receipt.jpg'));
    $unpaid = Student::where('status', 'active')->where('student_number', '!=', 'STD20240010')->orderByDesc('student_number')->first()->ensureUser();
    app(EventRegistrations::class)->register($this->event->fresh(), $unpaid);

    $b = app(PaymentBreakdown::class)->event($this->event->fresh());

    expect(collect($b['tiles'])->pluck('count', 'label')->filter()->all())->toMatchArray(['Registered' => 2, 'Paid' => 1, 'Not paid yet' => 1])
        ->and(collect($b['tiles'])->firstWhere('label', 'Fees collected')['sen'])->toBe(1500)
        ->and($b['check']['ok'])->toBeTrue()
        ->and(collect($b['rows'])->pluck('status', 'name')->all())->toBe([$this->student->name => 'Paid', $unpaid->name => 'Payment pending']);

    $this->actingAs($this->treasurer)->get("/clubs/{$this->club->slug}/finance/events/{$this->event->slug}")->assertOk();
    $this->actingAs($this->treasurer)->get("/clubs/{$this->club->slug}/finance/events/{$this->event->slug}/export/xlsx")->assertOk()->assertDownload();
    $this->actingAs($unpaid)->get("/clubs/{$this->club->slug}/finance/events/{$this->event->slug}")->assertForbidden();
});

it('counts membership joins by drive with the money collected', function () {
    MembershipDrive::create(['club_id' => null, 'title' => 'Welcome Week', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(6), 'set_by' => $this->treasurer->id]);
    $m = $this->club->memberships()->create(['user_id' => $this->student->id, 'status' => 'pending']);
    $payment = $m->payments()->create(['club_id' => $this->club->id, 'user_id' => $this->student->id, 'method' => 'online', 'amount_sen' => 3000, 'status' => PaymentStatus::Confirmed, 'verified_at' => now()]);
    app(MembershipService::class)->activate($payment);

    $b = app(PaymentBreakdown::class)->membership($this->club, app(SessionResolver::class)->current());

    expect($b['groups'][0]['items'][0])->toBe(['label' => 'Welcome Week ('.now()->subDay()->format('j M').' – '.now()->addDays(6)->format('j M').')', 'count' => 1, 'sen' => 3000])
        ->and($b['check']['ok'])->toBeTrue()
        ->and($b['rows'][0]['order_id'])->toBe('FIXTURE-CLUB_MEMBERSHIP_0001');
});
