<?php

use App\Enums\MembershipStatus;
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

    expect($query->counts($query->rows([$this->club->id])))->toMatchArray(['total' => 3, 'active' => 1, 'grace' => 1, 'terminated' => 1, 'unpaid' => 3]);
});

it('combines search and filters', function () {
    $query = app(MembersQuery::class);

    expect($query->rows([$this->club->id], ['search' => 'kavitha'])->pluck('student_number')->all())->toBe(['STD20240010'])
        ->and($query->rows([$this->club->id], ['status' => ['active', 'grace']]))->toHaveCount(2)
        ->and($query->rows([$this->club->id], ['expiring_days' => 30])->pluck('student_number')->all())->toBe(['STD20240010'])
        ->and($query->rows([$this->club->id], ['status' => ['active'], 'payment' => ['paid']]))->toHaveCount(0);
});

it('sorts on several levels', function () {
    $rows = app(MembersQuery::class)->rows([$this->club->id], ['sort' => [['status', 'asc'], ['name', 'desc']]]);

    expect($rows->pluck('status')->all())->toBe(['active', 'grace', 'terminated']);
});

it('keeps the members list to the club side and SLD', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->get("/clubs/{$this->club->slug}/members")->assertForbidden();
    $this->actingAs($this->f['president'])->get("/clubs/{$this->club->slug}/members?status[]=active")
        ->assertInertia(fn (Assert $p) => $p->component('Membership/Members')->has('rows', 1)->where('counts.total', 1));
    $this->actingAs(sldOfficer())->get('/admin/members')->assertInertia(fn (Assert $p) => $p->has('rows', 3));
});

it('exports into the official 10 Full Member List template', function () {
    $rows = app(MembersQuery::class)->rows([$this->club->id], ['sort' => [['name', 'asc']]]);
    $path = app(MemberListExport::class)->render($this->club, $rows);
    $sheet = IOFactory::load($path)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('Club Name:')
        ->and($sheet->getCell('B1')->getValue())->toBe('Fixture Club')
        ->and($sheet->getCell('B2')->getValue())->toBe('Dr Lim Siew Ling')
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
