<?php

use App\Services\Agm\Elections;
use App\Services\ClubStatusService;
use App\Services\Membership\CashHandovers;
use App\Services\Membership\MembershipLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Nightly camsys refresh, then year groups (the date moves even when camsys does not). CLAUDE.md §3–4.
Schedule::command('camsys:sync')->dailyAt('02:00')->withoutOverlapping()
    ->after(fn () => Artisan::call('students:recompute-year-groups'));

// Post-event documents: finance + receipts within 1 week, everything else within 2 weeks.
Schedule::command('events:send-reminders')->dailyAt('09:00');
// Approvals, proposal lead time, indemnity closing, AGM deadlines, replies to SLD.
Schedule::command('reminders:send')->dailyAt('09:05');

// Membership: grace, reminders, termination, alumni; stale cash handovers expire after 7 days.
Schedule::call(fn () => app(MembershipLifecycle::class)->run(CarbonImmutable::today()))->dailyAt('00:10')->name('membership-lifecycle');
Schedule::call(fn () => app(CashHandovers::class)->expireStale())->hourly()->name('expire-cash-handovers');

// Club status: Active / At-risk (2+ months without activity) / Inactive (3+ months).
Schedule::call(fn () => app(ClubStatusService::class)->run(CarbonImmutable::today()))->dailyAt('01:00')->name('club-status');

// Election winners take their positions when the new term starts (1 July).
Schedule::call(fn () => app(Elections::class)->handOverDue(CarbonImmutable::today()))->dailyAt('00:20')->name('election-handover');
