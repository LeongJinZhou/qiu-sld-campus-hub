<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Nightly camsys refresh, then year groups (the date moves even when camsys does not). CLAUDE.md §3–4.
Schedule::command('camsys:sync')->dailyAt('02:00')->withoutOverlapping()
    ->after(fn () => Artisan::call('students:recompute-year-groups'));
