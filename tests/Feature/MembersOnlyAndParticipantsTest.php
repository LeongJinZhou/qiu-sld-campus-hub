<?php

use App\Models\Attendance;
use App\Models\Student;
use App\Services\Events\EventRegistrations;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->event = $this->f['event'];
    $this->event->update(['registration_open' => true, 'status' => 'approved']);
    $this->president = $this->f['president'];
    $this->outsider = Student::where('student_number', 'STD20240010')->first()->ensureUser();
});

it('lets the committee make an event members-only, so non-members must join the club first', function () {
    $this->actingAs($this->outsider)->put("/events/{$this->event->slug}/registration-settings", ['members_only' => true])->assertForbidden();
    $this->actingAs($this->president)->put("/events/{$this->event->slug}/registration-settings", ['members_only' => true])->assertSessionHasNoErrors();

    $this->actingAs($this->outsider)->get("/events/{$this->event->slug}")->assertInertia(fn (Assert $p) => $p->where('event.members_only', true)->where('mayJoin', false));
    expect(fn () => app(EventRegistrations::class)->register($this->event->fresh(), $this->outsider))->toThrow(DomainException::class, 'members only');

    $this->club->memberships()->create(['user_id' => $this->outsider->id, 'status' => 'active', 'starts_on' => today(), 'expires_on' => today()->addYear()]);
    expect(app(EventRegistrations::class)->register($this->event->fresh(), $this->outsider)->exists)->toBeTrue();
});

it('shows the committee who joins events but not the club, with each person\'s event history', function () {
    app(EventRegistrations::class)->register($this->event->fresh(), $this->outsider);
    Attendance::create(['event_id' => $this->event->id, 'user_id' => $this->outsider->id, 'role' => 'participant', 'method' => 'qr', 'clock_in_at' => now()]);

    $this->actingAs($this->president)->get("/clubs/{$this->club->slug}/participants")->assertInertia(fn (Assert $p) => $p
        ->component('Clubs/Participants')
        ->where('events', 1)
        ->where('rows.0.name', $this->outsider->name)
        ->where('rows.0.member', 'none')
        ->where('rows.0.attended', 1)
        ->where('rows.0.events.0.name', 'Beach Cleanup'));
    $this->actingAs($this->president)->get("/clubs/{$this->club->slug}/participants/export/xlsx")->assertOk()->assertDownload();
    $this->actingAs($this->outsider)->get("/clubs/{$this->club->slug}/participants")->assertForbidden();
});
