<?php

use App\Enums\MembershipStatus;
use App\Models\Club;
use App\Models\Membership;
use App\Models\Student;
use App\Models\User;
use App\Services\Documents\MemberListExport;
use App\Services\Membership\MembersQuery;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    foreach (['STD20240010' => MembershipStatus::Active, 'STD20240009' => MembershipStatus::Grace, 'STD20240007' => MembershipStatus::Terminated] as $number => $status) {
        $user = Student::where('student_number', $number)->first()->ensureUser();
        Membership::create(['club_id' => $this->club->id, 'user_id' => $user->id, 'status' => $status, 'starts_on' => today()->subMonths(2), 'expires_on' => today()->addDays($status === MembershipStatus::Active ? 20 : -3)]);
    }
});

it('counts members by status and payment for the dashboard cards', function () {
    $query = app(MembersQuery::class);

    // Every member type is listed: the 3 members, plus the 4 committee members who have not joined and the advisor.
    expect($query->counts($query->rows([$this->club->id])))->toMatchArray(['total' => 8, 'active' => 1, 'grace' => 1, 'terminated' => 1, 'unpaid' => 7])
        ->and($query->rows([$this->club->id])->where('status', 'committee')->pluck('position')->sort()->values()->all())->toBe(['President', 'Secretary', 'Treasurer', 'Vice President'])
        ->and($query->rows([$this->club->id])->firstWhere('status', 'advisor'))->toMatchArray(['position' => 'Advisor', 'payment' => 'exempt']);
});

it('combines search and filters', function () {
    $query = app(MembersQuery::class);

    expect($query->rows([$this->club->id], ['search' => 'kavitha'])->pluck('student_number')->all())->toBe(['STD20240010'])
        ->and($query->rows([$this->club->id], ['status' => ['active', 'grace']]))->toHaveCount(2)
        ->and($query->rows([$this->club->id], ['expiring_days' => 30])->pluck('student_number')->all())->toBe(['STD20240010'])
        ->and($query->rows([$this->club->id], ['status' => ['active'], 'payment' => ['paid']]))->toHaveCount(0);
});

it('sorts on several levels', function () {
    $rows = app(MembersQuery::class)->rows([$this->club->id], ['status' => ['active', 'grace', 'terminated'], 'sort' => [['status', 'asc'], ['name', 'desc']]]);

    expect($rows->pluck('status')->all())->toBe(['active', 'grace', 'terminated']);
});

it('keeps the members list to the club side and SLD', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get("/clubs/{$this->club->slug}/members")->assertForbidden();
    $this->actingAs($this->f['president'])->get("/clubs/{$this->club->slug}/members?status[]=active")
        ->assertInertia(fn (Assert $p) => $p->component('Membership/Members')->has('rows', 1)->where('counts.total', 1));
    $this->actingAs(sldOfficer())->get('/admin/members')->assertInertia(fn (Assert $p) => $p->has('rows', 8)); // members + committee + advisor
    // Filters and sort as the page sends them (sort levels as "field:dir") are applied, not bounced back.
    $this->actingAs(sldOfficer())->get('/admin/members?status[]=grace&sort[]=name:asc')
        ->assertOk()->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('filters.sort.0', ['name', 'asc']));
    $this->actingAs(sldOfficer())->get('/admin/members?status[]=committee&club_id[]='.$this->club->id)->assertInertia(fn (Assert $p) => $p->has('rows', 4));
});

it('exports into the official 10 Full Member List template', function () {
    $rows = app(MembersQuery::class)->rows([$this->club->id], ['status' => ['active', 'grace', 'terminated'], 'sort' => [['name', 'asc']]]);
    $path = app(MemberListExport::class)->render($this->club, $rows);
    $sheet = IOFactory::load($path)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('Club Name: Fixture Club')
        ->and($sheet->getCell('A2')->getValue())->toBe('Advisor: Dr Lim Siew Ling')
        ->and($sheet->getCell('B4')->getValue())->toBe('Name')
        ->and($sheet->getCell('B5')->getValue())->toBe($rows[0]['name'])
        ->and($sheet->getCell('A8')->getValue())->toBeNull();
    unlink($path);
});

it('saves and reuses filter views per user', function () {
    $this->actingAs($this->f['president'])->post('/saved-views', ['name' => 'Expiring soon', 'filters' => ['expiring_days' => '30']])->assertSessionHas('success');

    $this->actingAs($this->f['president'])->get("/clubs/{$this->club->slug}/members")
        ->assertInertia(fn (Assert $p) => $p->where('views.0.name', 'Expiring soon'));
});

it('lists each person once across all clubs, with all their clubs together', function () {
    $other = Club::create(['name' => 'Second Club']);
    $kavitha = Student::where('student_number', 'STD20240010')->first()->ensureUser();
    Membership::create(['club_id' => $other->id, 'user_id' => $kavitha->id, 'status' => MembershipStatus::Active, 'starts_on' => today()->subMonth(), 'expires_on' => today()->addYear()]);

    $this->actingAs(sldOfficer())->get('/admin/members')->assertInertia(fn (Assert $p) => $p
        ->where('rows', fn ($rows) => collect($rows)->where('student_number', 'STD20240010')->count() === 1
            && collect(collect($rows)->firstWhere('student_number', 'STD20240010')['clubs'])->pluck('club')->sort()->values()->all() === ['Fixture Club', 'Second Club'])
        ->where('pagination.total', fn ($total) => $total === collect(app(MembersQuery::class)->rows(null))->pluck('student_number')->unique()->count()));

    // Filtering by one club lists only that club for the person; a club's own page is unchanged (one row per member).
    $this->actingAs(sldOfficer())->get("/admin/members?club_id[]={$other->id}")->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.clubs.0.club', 'Second Club')->where('counts.total', 1));
    $this->actingAs($this->f['president'])->get("/clubs/{$this->club->slug}/members")->assertInertia(fn (Assert $p) => $p->where('rows.0.club', fn ($c) => is_string($c)));
});
