<?php

use App\Enums\MembershipStatus;
use App\Models\ClubFee;
use App\Services\Membership\CommitteeFees;
use App\Services\Membership\MembershipService;
use App\Services\Notifications\ActionItems;
use App\Services\Time\SessionResolver;
use Illuminate\Auth\Events\Login;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->treasurer = $this->club->roleAssignments()->where('position', 'Treasurer')->first()->user;
    ClubFee::create(['club_id' => $this->club->id, 'session_id' => app(SessionResolver::class)->current()->id, 'amount_sen' => 3000, 'set_by' => $this->f['president']->id, 'approved_at' => now()]);
});

it('reminds a committee member who has not paid at every sign-in, with 7 days to pay', function () {
    $fees = app(CommitteeFees::class)->unpaid($this->treasurer);
    expect($fees)->toHaveCount(1)->and($fees[0])->toMatchArray(['position' => 'Treasurer', 'fee_sen' => 3000, 'days_left' => 7, 'overdue' => false])
        ->and(collect(app(ActionItems::class)->for($this->treasurer))->pluck('key'))->toContain("committee-fee:{$this->club->slug}");

    // Pop-up only on the first page after signing in.
    $this->actingAs($this->treasurer)->withSession(['committee_fee_popup' => true])->get('/feed')
        ->assertInertia(fn (Assert $p) => $p->has('committeeFees', 1)->where('committeeFees.0.club.slug', $this->club->slug));
    $this->actingAs($this->treasurer)->get('/feed')->assertInertia(fn (Assert $p) => $p->has('committeeFees', 0));
    event(new Login('web', $this->treasurer, false));
    expect(session('committee_fee_popup'))->toBeTrue();

    // The 7 days count from the first reminder.
    $this->travel(8)->days();
    expect(app(CommitteeFees::class)->unpaid($this->treasurer)[0]['overdue'])->toBeTrue();
});

it('stops reminding once the committee member has paid', function () {
    $membership = app(MembershipService::class)->join($this->club, $this->treasurer, true);
    $membership->update(['status' => MembershipStatus::Active]);

    expect(app(CommitteeFees::class)->unpaid($this->treasurer))->toBe([]);
});
