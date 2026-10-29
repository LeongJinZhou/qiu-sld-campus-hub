<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Nightly camsys refresh, then year groups (the date moves even when camsys does not). CLAUDE.md §3–4.
Schedule::command('camsys:sync')->dailyAt('02:00')->withoutOverlapping()
    ->after(fn () => Artisan::call('students:recompute-year-groups'));

// Post-event documents: finance + receipts within 1 week, everything else within 2 weeks.
Schedule::command('events:send-reminders')->dailyAt('09:00');
