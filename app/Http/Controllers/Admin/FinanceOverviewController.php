<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Services\Finance\Ledger;
use Inertia\Inertia;
use Inertia\Response;

/** SLD: every club's balance at a glance. */
class FinanceOverviewController extends Controller
{
    public function __invoke(Ledger $ledger): Response
    {
        return Inertia::render('Admin/Finance/Index', [
            'clubs' => Club::with('openingBalance')->orderBy('name')->get()->map(fn (Club $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'ready' => $c->openingBalance !== null,
                'balance_sen' => $ledger->balance($c),
                'last_entry' => $c->ledger()->max('entry_date'),
            ]),
        ]);
    }
}
