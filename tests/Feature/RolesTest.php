<?php

use App\Enums\ScopeType;
use App\Models\Club;
use App\Models\Student;
use App\Models\User;
use App\Services\Camsys\CamsysSync;
use App\Services\Roles\ClubTransfer;
use App\Services\Roles\CompulsoryPositionsCheck;
use App\Services\Roles\RoleService;
use App\Services\Roles\StaffAccounts;
use App\Services\Time\SessionResolver;

beforeEach(function () {
    seedCamsys();
    app(CamsysSync::class)->syncAll('test');
    $this->club = Club::create(['name' => 'Test Club']);
    $this->session = app(SessionResolver::class)->current();
    $this->roles = app(RoleService::class);
    $this->check = app(CompulsoryPositionsCheck::class);
});

function studentUser(string $number): User
{
    $student = Student::where('student_number', $number)->firstOrFail();
    $user = User::firstOrCreate(['email' => $student->email], ['name' => $student->name]);
    $student->update(['user_id' => $user->id]);

    return $user->load('student');
}

function fillCompulsory(object $test): void
{
    foreach (['STD20240001', 'STD20240002', 'STD20240003', 'STD20240004'] as $i => $number) {
        $test->roles->assignClubPosition($test->club, $test->session, studentUser($number), CompulsoryPositionsCheck::POSITIONS[$i]);
    }
}

it('lists all four compulsory positions as missing for a new club', function () {
    expect($this->check->missing(ScopeType::Club, $this->club->id, $this->session->id))->toBe(CompulsoryPositionsCheck::POSITIONS);
});

it('is complete once four different active students hold them', function () {
    fillCompulsory($this);

    expect($this->check->missing(ScopeType::Club, $this->club->id, $this->session->id))->toBe([]);
    $this->check->assertComplete(ScopeType::Club, $this->club->id, $this->session->id);
});

it('blocks submission while a compulsory position is vacant', function () {
    $this->roles->assignClubPosition($this->club, $this->session, studentUser('STD20240001'), 'President');

    $this->check->assertComplete(ScopeType::Club, $this->club->id, $this->session->id);
})->throws(DomainException::class, 'Vice President, Secretary, Treasurer');

it('refuses non-active students', function () {
    $this->roles->assignClubPosition($this->club, $this->session, studentUser('STD20220008'), 'President');
})->throws(DomainException::class, 'not an active student');

it('stops one student holding two positions in the same club and session', function () {
    $user = studentUser('STD20240001');
    $this->roles->assignClubPosition($this->club, $this->session, $user, 'President');

    $this->roles->assignClubPosition($this->club, $this->session, $user, 'Secretary');
})->throws(DomainException::class, 'already holds President');

it('replaces the previous holder of a position', function () {
    $this->roles->assignClubPosition($this->club, $this->session, studentUser('STD20240001'), 'President');
    $this->roles->assignClubPosition($this->club, $this->session, studentUser('STD20240002'), 'President');

    expect($this->club->roleAssignments()->where('position', 'President')->sole()->user->email)->toBe('meiling.tan@example.com');
});

it('allows optional positions beyond the compulsory four', function () {
    $assignment = $this->roles->assignClubPosition($this->club, $this->session, studentUser('STD20240005'), 'Publicity Director');

    expect($assignment->is_compulsory)->toBeFalse();
});

it('treats a holder who became alumni as a vacancy', function () {
    fillCompulsory($this);
    Student::where('student_number', 'STD20240004')->update(['status' => 'alumni']);

    expect($this->check->missing(ScopeType::Club, $this->club->id, $this->session->id))->toBe(['Treasurer']);
});

it('keeps committees per session', function () {
    fillCompulsory($this);
    $next = app(SessionResolver::class)->forName('2027/2028');

    expect($this->check->missing(ScopeType::Club, $this->club->id, $next->id))->toBe(CompulsoryPositionsCheck::POSITIONS);
});

it('only lets staff be advisors', function () {
    $this->roles->addAdvisor($this->club, $this->session, studentUser('STD20240001'));
})->throws(DomainException::class, 'staff account');

it('transfers a club only when the committee and advisor are complete, and SLD can take it back', function () {
    $transfer = app(ClubTransfer::class);
    expect(fn () => $transfer->transfer($this->club))->toThrow(DomainException::class);

    fillCompulsory($this);
    expect(fn () => $transfer->transfer($this->club))->toThrow(DomainException::class, 'advisor');

    $advisor = app(StaffAccounts::class)->create(['email' => 'adv@qiu.edu.my', 'name' => 'Dr Adv'])->user;
    $this->roles->addAdvisor($this->club, $this->session, $advisor);
    $transfer->transfer($this->club);
    expect($this->club->fresh()->isTransferred())->toBeTrue();

    $transfer->takeBack($this->club);
    expect($this->club->fresh()->isTransferred())->toBeFalse();
});

it('lets the committee edit the club only after transfer; SLD always can', function () {
    fillCompulsory($this);
    $president = studentUser('STD20240001');
    $outsider = studentUser('STD20240009');

    expect($president->can('update', $this->club))->toBeFalse()
        ->and(sldOfficer()->can('update', $this->club))->toBeTrue();

    $this->club->update(['managed_by' => 'committee']);

    expect($president->can('update', $this->club))->toBeTrue()
        ->and($outsider->can('update', $this->club))->toBeFalse();
});

it('writes role changes to the audit log', function () {
    $this->roles->assignClubPosition($this->club, $this->session, studentUser('STD20240001'), 'President');

    $this->assertDatabaseHas('audit_log', ['action' => 'committee.assigned', 'subject_type' => 'Club', 'subject_id' => $this->club->id]);
});
