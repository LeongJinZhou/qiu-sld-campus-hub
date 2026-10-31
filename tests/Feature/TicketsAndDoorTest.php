<?php

use App\Models\Attendance;
use App\Models\Student;
use App\Services\Ai\ReceiptReader;
use App\Services\Events\AttendanceQr;
use App\Services\Events\EventPayments;
use App\Services\Events\EventRegistrations;
use App\Services\Membership\ReceiptReading;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->f = proposalFixture();
    $this->event = $this->f['event'];
    $this->event->update(['registration_open' => true, 'status' => 'approved']);
    $this->president = $this->f['president'];
    $this->student = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    // An ordinary student: not the attendee, not on any committee.
    $template = Student::where('student_number', 'STD20240010')->first();
    $this->helper = Student::create([...$template->only($template->getFillable()), 'student_number' => 'STD20249999', 'email' => 'helper@qiu.edu.my', 'name' => 'Helper Student', 'user_id' => null])->ensureUser();
});

it('gives a free registration a ticket with its own order ID, shown only to its owner and the door', function () {
    $r = app(EventRegistrations::class)->register($this->event->fresh(), $this->student);

    expect($r->ticket_code)->toHaveLength(32)->and($r->order_id)->toBe('FIXTURE-CLUB_BEACH-CLEANUP_0001');

    $this->actingAs($this->student)->get("/tickets/{$r->ticket_code}")->assertInertia(fn (Assert $p) => $p
        ->component('Tickets/Show')->where('ticket.status', 'free')->where('ticket.order_id', $r->order_id)->where('mine', true));
    $this->actingAs($this->helper)->get("/tickets/{$r->ticket_code}")->assertForbidden();
    $this->actingAs($this->president)->get("/tickets/{$r->ticket_code}")->assertOk();

    // Scanning the ticket QR: the door desk for organisers, the ticket for its owner.
    $this->actingAs($this->president)->get("/t/{$r->ticket_code}")->assertRedirect(route('events.attendance', ['event' => $this->event, 'find' => $r->ticket_code]));
    $this->actingAs($this->student)->get("/t/{$r->ticket_code}")->assertRedirect(route('tickets.show', $r->ticket_code));
});

it('issues a paid ticket carrying the payment order ID once the fee is confirmed', function () {
    app(EventPayments::class)->configure($this->event, $this->president, ['fee_rm' => 15, 'payment_url' => 'https://pay.example/x', 'payment_account_name' => 'Fixture Club QIU']);
    $this->mock(ReceiptReader::class)->shouldReceive('read')->andReturn(new ReceiptReading(1500, today()->toDateString(), 'TXN-T1', 'FIXTURE CLUB QIU', 0.97));
    $r = app(EventRegistrations::class)->register($this->event->fresh(), $this->student);
    expect($r->ticket_code)->toBeNull();

    $payment = app(EventPayments::class)->submitOnline($r, UploadedFile::fake()->image('r.jpg'));

    expect($r->fresh()->ticket_code)->not->toBeNull()->and($r->fresh()->order_id)->toBe($payment->fresh()->order_id);
    $this->actingAs($this->student)->get('/activities')->assertInertia(fn (Assert $p) => $p
        ->component('Activities/Index')->where('tickets.0.status', 'paid')->where('tickets.0.amount_sen', 1500));
});

it('lets the door find someone by ticket ID and shows who has not paid', function () {
    app(EventPayments::class)->configure($this->event, $this->president, ['fee_rm' => 15, 'payment_url' => null, 'payment_account_name' => null]);
    app(EventRegistrations::class)->register($this->event->fresh(), $this->student);

    $this->actingAs($this->president)->get("/events/{$this->event->slug}/attendance?find=".urlencode($this->student->name))
        ->assertInertia(fn (Assert $p) => $p->component('Events/Attendance')
            ->where('results.0.name', $this->student->name)->where('results.0.status', 'unpaid')
            ->where('counts.unpaid', 1)->where('can.manage', true));
});

it('lets a student helper scan and clock people in, but not manage the desk', function () {
    $this->event->update(['attendance_open' => true, 'starts_at' => now(), 'ends_at' => now()->addHours(2)]);
    $r = app(EventRegistrations::class)->register($this->event->fresh(), $this->student);
    $this->actingAs($this->helper)->get("/events/{$this->event->slug}/attendance")->assertForbidden();

    $this->actingAs($this->president)->post("/events/{$this->event->slug}/helpers", ['student_number' => $this->helper->student->student_number])->assertSessionHasNoErrors();

    $this->actingAs($this->helper)->get("/events/{$this->event->slug}/attendance")->assertInertia(fn (Assert $p) => $p->where('can.manage', false)->where('helpers', []));
    $this->actingAs($this->helper)->post("/events/{$this->event->slug}/attendance", ['action' => 'in', 'code' => route('tickets.scan', $r->ticket_code)])
        ->assertRedirect(route('events.attendance', ['event' => $this->event, 'find' => $this->student->student->student_number, 'action' => 'in']));
    expect(Attendance::where('event_id', $this->event->id)->where('user_id', $this->student->id)->value('clock_in_at'))->not->toBeNull();

    $this->actingAs($this->helper)->put("/events/{$this->event->slug}/attendance/settings", ['qr_rotate_seconds' => 15])->assertForbidden();
    $this->actingAs($this->helper)->post("/events/{$this->event->slug}/helpers", ['student_number' => 'X'])->assertForbidden();
});

it('rotates the screen QR at the pace the committee sets', function () {
    $this->actingAs($this->president)->put("/events/{$this->event->slug}/attendance/settings", ['qr_rotate_seconds' => 15])->assertSessionHasNoErrors();
    $this->actingAs($this->president)->put("/events/{$this->event->slug}/attendance/settings", ['qr_rotate_seconds' => 5])->assertSessionHasErrors('qr_rotate_seconds');
    $event = $this->event->fresh();
    $qr = app(AttendanceQr::class);
    $this->travelTo(now()->startOfMinute());
    $code = $qr->code($event, 'in');

    $this->travel(16)->seconds();
    expect($qr->isValid($event, 'in', $code))->toBeTrue(); // previous slot still accepted

    $this->travel(15)->seconds();
    expect($qr->isValid($event, 'in', $code))->toBeFalse(); // a shared photo is useless after two turns
});
