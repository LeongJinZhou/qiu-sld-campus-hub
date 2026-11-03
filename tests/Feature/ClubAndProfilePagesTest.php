<?php

use App\Models\Club;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\Camsys\CamsysSync;
use App\Services\Profiles\PublicProfile;
use App\Services\Roles\RoleService;
use App\Services\Time\SessionResolver;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedCamsys();
    $this->travelTo('2026-10-29 10:00:00');
    app(CamsysSync::class)->syncAll('test');
    $this->student = Student::where('student_number', 'STD20240001')->first();
    $this->user = User::create(['email' => $this->student->email, 'name' => $this->student->name])->assignRole('student');
    $this->student->update(['user_id' => $this->user->id]);
    $this->club = Club::create(['name' => 'GPS Club', 'instagram_handle' => 'gps_qiu']);
    app(RoleService::class)->assignClubPosition($this->club, app(SessionResolver::class)->current(), $this->user->load('student'), 'President');
});

it('sends guests scanning a club QR to sign in, then back to the club', function () {
    $this->get("/c/{$this->club->qr_token}")->assertRedirect(route('login'));

    $this->actingAs($this->user)->get("/c/{$this->club->qr_token}")->assertRedirect(route('clubs.show', $this->club));
});

it('shows the committee with photo, position, course short form and year group', function () {
    $this->actingAs($this->user)->get("/clubs/{$this->club->slug}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clubs/Show')
            ->where('session', '2026/2027')
            ->where('committee.0.position', 'President')
            ->where('committee.0.name', 'Ahmad Bin Razak')
            ->where('committee.0.programme_short', 'BCS')
            ->where('committee.0.year_group', 'Year 3')
            ->has('committee.0.photo_url')
            ->where('missingCompulsory', ['Vice President', 'Secretary', 'Treasurer']));
});

it('serves the public profile by permanent QR token with portfolio fields only', function () {
    $this->get("/p/{$this->user->qr_token}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Public')
            ->where('profile.name', 'Ahmad Bin Razak')
            ->where('profile.year_group', 'Year 3')
            ->where('profile.positions.0.position', 'President')
            ->missing('profile.mobile_no')
            ->missing('profile.email'));
});

it('hides sections the student turned off', function () {
    $this->user->update(['hidden_sections' => ['positions']]);

    $this->get("/p/{$this->user->qr_token}")
        ->assertInertia(fn (Assert $page) => $page->where('profile.positions', null)->has('profile.clubs', 1));
});

it('accepts a personal email but not a QIU one', function () {
    $this->actingAs($this->user)->post('/me', ['personal_email' => 'ahmad@qiu.edu.my'])->assertSessionHasErrors('personal_email');
    $this->actingAs($this->user)->post('/me', ['personal_email' => 'ahmad@gmail.com', 'hidden_sections' => ['clubs']])->assertSessionHasNoErrors();

    expect($this->user->fresh()->personal_email)->toBe('ahmad@gmail.com')
        ->and($this->user->fresh()->hidden_sections)->toBe(['clubs']);
});

it('keeps admin pages to SLD and super-admin pages to Super Admin', function () {
    $this->actingAs($this->user)->get('/admin/clubs')->assertForbidden();
    $this->actingAs(sldOfficer())->get('/admin/clubs')->assertOk();
    $this->actingAs(sldOfficer())->get('/admin/audit')->assertForbidden();
    $this->actingAs(superAdmin())->get('/admin/audit')->assertOk();
});

it('lets SLD assign a committee position by student ID', function () {
    $this->actingAs(sldOfficer())
        ->post("/admin/clubs/{$this->club->slug}/committee", ['session' => '2026/2027', 'student_number' => 'STD20240002', 'position' => 'Treasurer'])
        ->assertSessionHas('success');

    expect($this->club->roleAssignments()->where('position', 'Treasurer')->sole()->user->name)->toBe('Tan Mei Ling');
});

it('lets a Super Admin grant SLD Officer but not change their own roles', function () {
    $boss = superAdmin();

    $this->actingAs($boss)->put("/admin/roles/{$this->user->id}", ['roles' => ['admin']])->assertSessionHas('success');
    expect($this->user->fresh()->hasRole('admin'))->toBeTrue();

    $this->actingAs($boss)->put("/admin/roles/{$boss->id}", ['roles' => []])->assertForbidden();
});

it('lets an SLD Officer add and manage SLD admins, but never Super Admins or herself', function () {
    $officer = sldOfficer();
    $boss = superAdmin();

    $this->actingAs($officer)->get('/admin/roles')->assertInertia(fn ($page) => $page->where('managedRoles', [['value' => 'admin', 'label' => 'SLD Officer']]));
    $this->actingAs($officer)->post('/admin/roles', ['name' => 'New Officer', 'email' => 'new.officer@qiu.edu.my'])->assertSessionHas('success');
    $new = User::where('email', 'new.officer@qiu.edu.my')->sole();
    expect($new->hasRole('admin'))->toBeTrue()->and($new->hasRole('staff'))->toBeTrue()
        ->and(Staff::where('email', 'new.officer@qiu.edu.my')->value('active'))->toBeTrue();
    $this->actingAs($officer)->post('/admin/roles', ['name' => 'Outsider', 'email' => 'someone@gmail.com'])->assertSessionHasErrors('email');

    $this->actingAs($officer)->put("/admin/roles/{$new->id}", ['roles' => []])->assertSessionHas('success');
    expect($new->fresh()->hasRole('admin'))->toBeFalse();
    $this->actingAs($officer)->put("/admin/roles/{$this->user->id}", ['roles' => ['admin', 'superadmin']])->assertSessionHas('success');
    expect($this->user->fresh()->hasRole('admin'))->toBeTrue()->and($this->user->fresh()->hasRole('superadmin'))->toBeFalse();
    $this->actingAs($officer)->put("/admin/roles/{$boss->id}", ['roles' => []])->assertForbidden();
    $this->actingAs($officer)->put("/admin/roles/{$officer->id}", ['roles' => []])->assertForbidden();
    expect($boss->fresh()->hasRole('superadmin'))->toBeTrue();
});

it('lists clubs a student simply joined on their profile, and keeps hidden members off the club member wall', function () {
    $plain = Student::where('student_number', 'STD20240002')->first()->ensureUser();
    $shy = Student::where('student_number', 'STD20240003')->first()->ensureUser();
    $shy->update(['hidden_sections' => ['clubs']]);
    foreach ([$plain, $shy] as $member) {
        $this->club->memberships()->create(['user_id' => $member->id, 'status' => 'active', 'starts_on' => today(), 'expires_on' => today()->addDays(365)]);
    }

    expect(app(PublicProfile::class)->for($plain)['clubs'])
        ->toMatchArray([['name' => 'GPS Club', 'url' => route('clubs.show', $this->club), 'logo_url' => $this->club->logoUrl(), 'category' => null, 'current_position' => null, 'membership' => 'Member since Oct 2026']]);

    $this->actingAs($this->user)->get("/clubs/{$this->club->slug}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.members', 2)
            ->has('memberWall', 1)
            ->where('memberWall.0.name', $plain->name));
});
