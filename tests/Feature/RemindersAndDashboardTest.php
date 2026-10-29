<?php

use App\Enums\DocumentStatus;
use App\Models\Agm;
use App\Models\SldRequest;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Reminders\Reminders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->f = proposalFixture();
    $this->club = $this->f['club'];
    $this->sld = sldOfficer();
});

function remindersOn(string $date): int
{
    test()->travelTo(CarbonImmutable::parse($date)->setTime(9, 5));

    return app(Reminders::class)->run(CarbonImmutable::parse($date));
}

it('reminds the event committee 7 days and 1 day before the last day to submit a proposal, once each', function () {
    $event = $this->f['event']->fresh();
    $event->update(['expected_participants' => 40]);
    $lastDay = CarbonImmutable::parse($event->starts_at)->startOfDay()->subMonthsNoOverflow(1)->subDays(15);

    expect(remindersOn($lastDay->subDays(7)->toDateString()))->toBe(1)
        ->and(remindersOn($lastDay->subDays(7)->toDateString()))->toBe(0)
        ->and(remindersOn($lastDay->subDays(3)->toDateString()))->toBe(0)
        ->and(remindersOn($lastDay->subDay()->toDateString()))->toBe(1);
    $secretary = $event->committee()->where('position', 'Secretary')->first()->user;
    Notification::assertSentTo($secretary, DocumentNotification::class, fn ($n) => str_contains($n->title, 'Submit the proposal'));
});

it('chases approvers who are due tomorrow and daily while overdue', function () {
    $this->actingAs($this->f['president'])->post("/events/{$this->f['event']->slug}/proposal/submit", [...completeProposal($this->f), 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()]);
    $step = $this->f['document']->fresh()->approvals()->where('step_no', 2)->first();
    expect($step->due_at)->not->toBeNull();

    expect(remindersOn($step->due_at->subDay()->toDateString()))->toBe(1)
        ->and(remindersOn($step->due_at->addDay()->toDateString()))->toBe(1)
        ->and(remindersOn($step->due_at->addDays(2)->toDateString()))->toBe(1);
    Notification::assertSentTo($this->f['advisor'], DocumentNotification::class, fn ($n) => str_starts_with($n->title, 'Overdue: '));
});

it('tracks SLD requests: club replies within 7 working days, reminders when due and overdue', function () {
    $this->actingAs($this->sld)->post('/admin/requests', ['club_ids' => [$this->club->id], 'subject' => 'Updated committee list', 'body' => 'Please confirm.'])->assertSessionHas('success');
    $request = SldRequest::sole();
    expect($request->due_on->toDateString())->toBe(today()->addWeekdays(7)->toDateString());

    expect(remindersOn($request->due_on->subDay()->toDateString()))->toBe(1)
        ->and(remindersOn($request->due_on->addDays(2)->toDateString()))->toBe(1)
        ->and($request->fresh()->isOverdue())->toBeTrue();
    $this->actingAs($this->sld)->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->where('overdue.sld_requests', 1));

    $this->actingAs(User::factory()->create())->post("/sld-requests/{$request->id}/reply", ['reply' => 'x'])->assertForbidden();
    $this->actingAs($this->f['president'])->post("/sld-requests/{$request->id}/reply", ['reply' => 'Confirmed.'])->assertSessionHas('success');
    $this->actingAs($this->f['president'])->post("/sld-requests/{$request->id}/reply", ['reply' => 'Again'])->assertSessionHas('error');
    expect(remindersOn($request->due_on->addDays(3)->toDateString()))->toBe(0);
});

it('reminds clubs without an AGM a week before the AGM deadline', function () {
    $this->secretary = $this->club->roleAssignments()->where('position', 'Secretary')->first()->user;
    $agm = openPeriodAndAgm($this);
    $period = $agm->period;
    Agm::query()->delete();

    expect(remindersOn($period->agm_deadline->subDays(7)->toDateString()))->toBeGreaterThanOrEqual(1);
    Notification::assertSentTo($this->secretary, DocumentNotification::class, fn ($n) => $n->title === 'Schedule your AGM');
});

it('reminds people who have not signed the indemnity 2 days before signing closes', function () {
    $event = $this->f['event'];
    $event->update(['is_outdoor' => true, 'status' => DocumentStatus::Approved, 'starts_at' => today()->addDays(5)->setTime(8, 0), 'ends_at' => today()->addDays(5)->setTime(12, 0)]);
    expect(remindersOn(today()->toDateString()))->toBe(1)->and(remindersOn(today()->toDateString()))->toBe(0);
});

it('shows SLD the dashboard and keeps it from everyone else', function () {
    $this->actingAs($this->sld)->get('/admin')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Dashboard')->has('clubs_by_status')->has('balances'));
    $this->actingAs($this->f['president'])->get('/admin')->assertForbidden();
    $this->actingAs($this->f['advisor'])->get('/approvals')->assertOk()->assertInertia(fn ($page) => $page->has('stats.waiting'));
});
