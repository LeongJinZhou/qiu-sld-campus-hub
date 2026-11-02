<?php

use App\Models\Club;
use App\Models\ClubApplication;
use App\Models\RoleAssignment;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    proposalFixture();
    $this->president = Student::where('student_number', 'STD20240010')->firstOrFail()->ensureUser();
    $this->others = [otherStudent('STD20249991', 'Vice Person'), otherStudent('STD20249992', 'Sec Person'), otherStudent('STD20249993', 'Treas Person')];
    $this->advisor = User::where('email', 'advisor2.demo@qiu.edu.my')->firstOrFail();
    $this->payload = fn (array $extra = []) => [
        'name' => 'Robotics Society', 'category_id' => null, 'bio' => 'We build robots.', 'objectives' => "Weekly builds\nAnnual competition",
        'officers' => ['President' => 'STD20240010', 'Vice President' => 'STD20249991', 'Secretary' => 'STD20249992', 'Treasurer' => 'STD20249993'],
        'advisor_id' => $this->advisor->id, 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes(), ...$extra,
    ];
});

it('opens a new club only after the President applies, the advisor signs and SLD approves', function () {
    $this->actingAs($this->president)->get('/clubs-apply')->assertInertia(fn (Assert $p) => $p->component('Clubs/Apply')->where('me.student_number', 'STD20240010'));
    $this->actingAs($this->president)->post('/clubs-apply', ($this->payload)())->assertRedirect();
    $application = ClubApplication::firstOrFail();
    expect($application->status)->toBe('advisor')->and(Club::where('name', 'Robotics Society')->exists())->toBeFalse();

    // Nobody but the advisor signs; SLD cannot decide before the advisor.
    $this->actingAs($this->others[0])->get("/club-applications/{$application->id}")->assertForbidden();
    $this->actingAs(sldOfficer())->post("/club-applications/{$application->id}/decide", ['approve' => true])->assertSessionHas('error');

    $this->actingAs($this->advisor)->get("/club-applications/{$application->id}")->assertInertia(fn (Assert $p) => $p->component('Clubs/Application')->where('can.sign', true));
    $this->actingAs($this->advisor)->post("/club-applications/{$application->id}/sign", ['signature' => signaturePng(), 'signature_strokes' => signatureStrokes()])->assertSessionHas('success');
    expect($application->fresh()->status)->toBe('sld');

    $this->actingAs(sldOfficer())->post("/club-applications/{$application->id}/decide", ['approve' => true])->assertSessionHas('success');
    $club = Club::where('name', 'Robotics Society')->firstOrFail();
    expect($application->fresh()->status)->toBe('approved')
        ->and($club->status)->toBe('active')
        ->and(RoleAssignment::where('scope_id', $club->id)->where('position', 'President')->value('user_id'))->toBe($this->president->id)
        ->and(RoleAssignment::where('scope_id', $club->id)->where('user_id', $this->advisor->id)->exists())->toBeTrue();
});

it('returns an application for changes, then accepts the corrected and re-signed one', function () {
    $this->actingAs($this->president)->post('/clubs-apply', ($this->payload)());
    $application = ClubApplication::firstOrFail();
    $this->actingAs($this->advisor)->post("/club-applications/{$application->id}/return", ['note' => 'Add a safety plan.'])->assertSessionHas('success');
    expect($application->fresh()->status)->toBe('returned');

    $this->actingAs($this->president)->post("/club-applications/{$application->id}/resubmit", ($this->payload)(['objectives' => 'Weekly builds with a safety plan']))->assertSessionHas('success');
    expect($application->fresh())->status->toBe('advisor')->note->toBeNull()
        ->and(ClubApplication::count())->toBe(1);
});

it('refuses a duplicate club name, a missing officer and an applicant who is not the President', function () {
    $existing = Club::firstOrFail()->name;
    $this->actingAs($this->president)->post('/clubs-apply', ($this->payload)(['name' => strtoupper($existing)]))->assertSessionHas('error');
    $this->actingAs($this->president)->post('/clubs-apply', ($this->payload)(['officers' => ['President' => 'STD20240010', 'Vice President' => 'STD20249991', 'Secretary' => 'NOPE', 'Treasurer' => 'STD20249993']]))->assertSessionHas('error');
    $this->actingAs($this->others[0])->post('/clubs-apply', ($this->payload)())->assertSessionHas('error');
    expect(ClubApplication::count())->toBe(0);
});

it('lets SLD reject with a reason', function () {
    $this->actingAs($this->president)->post('/clubs-apply', ($this->payload)());
    $application = ClubApplication::firstOrFail();
    $this->actingAs($this->advisor)->post("/club-applications/{$application->id}/sign", ['signature' => signaturePng(), 'signature_strokes' => signatureStrokes()]);
    $this->actingAs(sldOfficer())->post("/club-applications/{$application->id}/decide", ['approve' => false])->assertSessionHasErrors('note');
    $this->actingAs(sldOfficer())->post("/club-applications/{$application->id}/decide", ['approve' => false, 'note' => 'Overlaps an existing club.'])->assertSessionHas('success');
    expect($application->fresh()->status)->toBe('rejected')->and(User::count())->toBeGreaterThan(0);
});
