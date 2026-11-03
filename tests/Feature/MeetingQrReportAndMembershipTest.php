<?php

use App\Enums\MembershipStatus;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\Staff;
use App\Notifications\DocumentNotification;
use App\Services\Events\AttendanceQr;
use App\Services\Meetings\Meetings;
use App\Services\Membership\MembershipLifecycle;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->secretary = $this->club->roleAssignments()->where('position', 'Secretary')->first()->user;
});

function qrMeeting(object $t, string $kind = 'committee', string $audience = 'committee'): Meeting
{
    return app(Meetings::class)->create($t->club, $t->secretary, [
        'kind' => $kind, 'title' => 'Weekly sync', 'starts_at' => now()->addHour()->toDateTimeString(),
        'ends_at' => now()->addHours(3)->toDateTimeString(), 'venue' => 'Student Lounge', 'online' => false,
        'audience' => $audience, 'pax' => 20,
    ]);
}

function scan(Meeting $m, string $action): string
{
    return "/meetings/{$m->id}/attend?action={$action}&code=".app(AttendanceQr::class)->code($m, $action);
}

it('lets the committee and advisor check in and out of a committee meeting by QR, and refuses members', function () {
    $meeting = qrMeeting($this);
    [$member] = plainMembers($this, 1);
    $this->travel(40)->minutes(); // inside the 30-minute early window

    $this->actingAs($this->f['advisor'])->get(scan($meeting, 'in'))->assertSessionHas('success');
    $this->actingAs($member)->get(scan($meeting, 'in'))->assertSessionHas('error', 'This meeting is for the Fixture Club committee and advisors only.');
    $this->actingAs($this->f['advisor'])->get(scan($meeting, 'in'))->assertSessionHas('error');

    $this->actingAs($this->f['advisor'])->get(scan($meeting, 'out'))->assertSessionHas('error', 'Check-out opens when the meeting starts.');
    $this->travel(30)->minutes();
    $this->actingAs($this->f['advisor'])->get(scan($meeting, 'out'))->assertSessionHas('success');

    $row = $meeting->attendees()->whereKey($this->f['advisor']->id)->first()->pivot;
    expect((bool) $row->present)->toBeTrue()->and($row->checked_in_at)->not->toBeNull()->and($row->checked_out_at)->not->toBeNull()->and($row->method)->toBe('qr')
        ->and($meeting->attendees()->whereKey($member->id)->exists())->toBeFalse();
});

it('lets every member check in to an all-members activity, but not outsiders', function () {
    $meeting = qrMeeting($this, 'activity', 'members');
    [$member] = plainMembers($this, 1);
    $outsider = otherStudent();
    $this->travel(50)->minutes();

    $this->actingAs($member)->get(scan($meeting, 'in'))->assertSessionHas('success');
    $this->actingAs($outsider)->get(scan($meeting, 'in'))->assertSessionHas('error', 'This activity is for Fixture Club members only.');
});

it('refuses an expired or forged code and a check-in before the window opens', function () {
    $meeting = qrMeeting($this);
    $this->actingAs($this->f['advisor'])->get(scan($meeting, 'in'))->assertSessionHas('error', 'Check-in opens 30 minutes before the meeting starts.');
    $this->actingAs($this->f['advisor'])->get("/meetings/{$meeting->id}/attend?action=in&code=forged")->assertSessionHas('error', 'This QR code has expired. Scan the code on screen again.');
    $code = app(AttendanceQr::class)->code($meeting, 'in');
    $this->travel(40)->minutes();
    $this->actingAs($this->f['advisor'])->get("/meetings/{$meeting->id}/attend?action=in&code={$code}")->assertSessionHas('error', 'This QR code has expired. Scan the code on screen again.');
});

it('shows the QR to the committee while the meeting is on, and keeps the secret out of the page', function () {
    $meeting = qrMeeting($this);
    $this->travel(40)->minutes();

    $this->actingAs($this->secretary)->get("/meetings/{$meeting->id}")->assertInertia(fn ($page) => $page->whereNot('qr', null)->has('qr.in')->has('qr.out'));
    expect($meeting->fresh()->toArray())->not->toHaveKey('attendance_secret');
});

it('submits signed minutes with the signature after a draft was saved', function () {
    $meeting = qrMeeting($this);
    $payload = ['purpose' => 'Sync', 'summary' => ['Discussed'], 'actions' => [], 'next_date' => null, 'next_time' => '', 'next_venue' => '', 'notes' => ''];
    $this->actingAs($this->secretary)->put("/meetings/{$meeting->id}/minutes", $payload)->assertSessionHas('success');
    $this->actingAs($this->secretary)->post("/meetings/{$meeting->id}/minutes/submit", [...$payload, 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()])
        ->assertSessionHasNoErrors()->assertSessionHas('success');
});

it('reports meetings for the club and for SLD, and exports them', function () {
    $meeting = qrMeeting($this, 'activity', 'members');
    $this->travel(50)->minutes();
    $this->actingAs($this->f['advisor'])->get(scan($meeting, 'in'));

    $this->actingAs($this->secretary)->get("/clubs/{$this->club->slug}/meetings/report")
        ->assertInertia(fn ($page) => $page->component('Meetings/Report')->where('meetings.data.0.present', 1)->where('totals.activities', 1));
    $this->actingAs(sldOfficer())->get('/admin/meetings?kind=activity')
        ->assertInertia(fn ($page) => $page->where('meetings.data.0.club', 'Fixture Club')->has('clubs'));
    $this->actingAs(sldOfficer())->get('/admin/meetings/export.xlsx')->assertOk()->assertDownload();
    $outsider = otherStudent();
    $this->actingAs($outsider)->get("/clubs/{$this->club->slug}/meetings/report")->assertForbidden();
    $this->actingAs($outsider)->get('/admin/meetings')->assertForbidden();
});

it('lets SLD add any active staff member as advisor, even one who never signed in', function () {
    $club = Club::create(['name' => 'Brand New Club']);
    $staff = Staff::create(['email' => 'new.lecturer@qiu.edu.my', 'name' => 'New Lecturer', 'title' => 'Lecturer', 'active' => true]);

    $this->actingAs(sldOfficer())->post("/admin/clubs/{$club->slug}/advisors", ['staff_id' => $staff->id, 'session' => app(SessionResolver::class)->current()->name])
        ->assertSessionHasNoErrors()->assertSessionMissing('error')->assertSessionHas('success', 'New Lecturer is now an advisor.');
    expect($staff->fresh()->user_id)->not->toBeNull()
        ->and($club->roleAssignments()->where('user_id', $staff->fresh()->user_id)->exists())->toBeTrue();
    $this->actingAs(sldOfficer())->get("/admin/clubs/{$club->slug}/edit")->assertInertia(fn ($page) => $page->where('staff', fn ($s) => collect($s)->contains('id', $staff->id)));
});

it('keeps the grace end date fixed while reminding, then terminates and tells the member', function () {
    Notification::fake();
    [$member] = plainMembers($this, 1);
    $m = Membership::where('user_id', $member->id)->first();
    $m->update(['expires_on' => today()]);
    $lifecycle = app(MembershipLifecycle::class);

    $lifecycle->run(CarbonImmutable::today());
    $graceEnds = $m->fresh()->grace_ends_on->toDateString();
    $lifecycle->run(CarbonImmutable::today()->addDays(3));
    expect($m->fresh()->grace_ends_on->toDateString())->toBe($graceEnds);

    $lifecycle->run(CarbonImmutable::today()->addDays(7));
    expect($m->fresh()->status)->toBe(MembershipStatus::Terminated);
    Notification::assertSentTo($member, DocumentNotification::class, fn (DocumentNotification $n) => str_contains($n->title, 'has ended'));
});

it('lets the committee remind, extend, end, mark alumni and undo it, and keeps outsiders out', function () {
    Notification::fake();
    [$member] = plainMembers($this, 1, 'grace');
    $m = Membership::where('user_id', $member->id)->first();
    $m->update(['expires_on' => today()->subDay(), 'grace_ends_on' => today()->addDays(6)]);
    $url = "/clubs/{$this->club->slug}/memberships/{$m->id}/status";

    $this->actingAs($this->secretary)->post($url, ['action' => 'remind'])->assertSessionHas('success');
    $this->actingAs($this->secretary)->post($url, ['action' => 'extend', 'days' => 10])->assertSessionHas('success');
    expect($m->fresh()->grace_ends_on->toDateString())->toBe(today()->addDays(16)->toDateString());
    $this->actingAs($this->secretary)->post($url, ['action' => 'terminate'])->assertSessionHasErrors('reason');
    $this->actingAs($this->secretary)->post($url, ['action' => 'terminate', 'reason' => 'Left the university.'])->assertSessionHas('success');
    expect($m->fresh()->status)->toBe(MembershipStatus::Terminated);
    $this->actingAs($this->secretary)->post($url, ['action' => 'extend', 'days' => 3])->assertSessionHas('error');
    $this->actingAs(sldOfficer())->post($url, ['action' => 'alumni'])->assertSessionHas('success');
    expect($m->fresh()->status)->toBe(MembershipStatus::Alumni);
    $this->actingAs($this->secretary)->post($url, ['action' => 'restore'])->assertSessionHas('success');
    expect($m->fresh()->status)->toBe(MembershipStatus::Grace);

    $this->actingAs($member)->post($url, ['action' => 'alumni'])->assertForbidden();
    $other = Club::create(['name' => 'Other Club']);
    $this->actingAs(sldOfficer())->post("/clubs/{$other->slug}/memberships/{$m->id}/status", ['action' => 'remind'])->assertNotFound();
});
