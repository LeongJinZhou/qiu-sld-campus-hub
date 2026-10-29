<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CamsysSyncRun;
use App\Services\Camsys\CamsysSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SyncController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Sync/Index', [
            'runs' => CamsysSyncRun::latest('id')->limit(50)->get()->map(fn (CamsysSyncRun $r) => [
                ...$r->only('id', 'trigger', 'records_seen', 'upserted', 'error'),
                'started_at' => $r->started_at->toDateTimeString(),
                'seconds' => $r->finished_at?->diffInSeconds($r->started_at, absolute: true),
            ]),
        ]);
    }

    public function store(Request $request, CamsysSync $sync): RedirectResponse
    {
        $run = $sync->syncAll('manual', $request->user());
        $sync->recomputeYearGroups();

        return back()->with($run->error ? 'error' : 'success', $run->error ?? "Synced {$run->upserted} of {$run->records_seen} students.");
    }
}
