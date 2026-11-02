<?php

use App\Enums\DocumentStatus;
use App\Models\Meeting;
use App\Services\Dashboard\ClubTracking;
use App\Services\Finance\Ledger;
use App\Services\Time\SessionResolver;
use Inertia\Testing\AssertableInertia as Assert;

it('tracks missing weekly reports by month, incomplete event packages and the AGM for every club', function () {
    $f = proposalFixture();
    $session = app(SessionResolver::class)->current();
    Meeting::create(['club_id' => $f['club']->id, 'session_id' => $session->id, 'kind' => 'activity', 'title' => 'Training', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(3)->addHours(2), 'organiser_id' => $f['president']->id]);
    $f['event']->update(['status' => DocumentStatus::Approved, 'starts_at' => now()->subDays(20), 'ends_at' => now()->subDays(20)->addHours(3)]);
    $f['event']->documentItems()->create(['kind' => 'post_mortem', 'due_on' => now()->subDays(6)->toDateString()]);

    $data = app(ClubTracking::class)->build($session);
    $row = collect($data['clubs'])->firstWhere('slug', $f['club']->slug);
    expect($row['weekly_missing'])->toBe(1)
        ->and($row['events_incomplete'])->toBe(1)
        ->and($row['events'][0]['overdue'])->toBeTrue()
        ->and($row['events'][0]['missing'])->toContain('Post mortem')->toContain('Photos (3)');

    $this->actingAs(sldOfficer())->get('/admin/tracking')->assertInertia(fn (Assert $p) => $p->component('Admin/Tracking/Index')->where('totals.weekly_missing', 1));
    $this->actingAs($f['president'])->get('/admin/tracking')->assertForbidden();
});

it('shows SLD money month by month and year by year, leaving advances out', function () {
    $f = proposalFixture();
    $ledger = app(Ledger::class);
    $ledger->setOpeningBalance($f['club'], 100000, app(SessionResolver::class)->current()->starts_on, null, sldOfficer());
    $ledger->income($f['club'], today(), 'Fees', 3000, category: 'membership');

    $money = app(ClubTracking::class)->money(app(SessionResolver::class)->current());
    expect(collect($money['monthly'])->sum('income_sen'))->toBe(3000)->and(collect($money['yearly'])->sum('income_sen'))->toBe(3000);
    $this->actingAs(sldOfficer())->get('/admin/finance')->assertInertia(fn (Assert $p) => $p->has('money.monthly', 12));
});
