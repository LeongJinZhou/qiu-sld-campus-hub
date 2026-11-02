<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Services\Dashboard\ClubTracking;
use App\Services\Finance\Ledger;
use App\Services\Finance\TransactionSearch;
use App\Services\Time\SessionResolver;
use App\Support\QueryInput;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** SLD: every club's balance at a glance, and a finder for any transaction by any part of its ID. */
class FinanceOverviewController extends Controller
{
    public function __invoke(Request $request, Ledger $ledger, TransactionSearch $search): Response
    {
        $q = QueryInput::text($request, 'q');

        return Inertia::render('Admin/Finance/Index', [
            'q' => $q,
            'transactions' => $q === '' ? [] : $search->find($q),
            // Money month by month (this session) and year by year, all clubs or one (?club=slug).
            'money' => fn () => app(ClubTracking::class)->money(app(SessionResolver::class)->current(), ($slug = QueryInput::text($request, 'club', 100)) ? Club::where('slug', $slug)->first() : null),
            'moneyClub' => QueryInput::text($request, 'club', 100) ?: null,
            'clubs' => fn () => Club::with('openingBalance')->orderBy('name')->get()->map(fn (Club $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'ready' => $c->openingBalance !== null,
                'balance_sen' => $ledger->balance($c),
                'last_entry' => $c->ledger()->max('entry_date'),
            ]),
        ]);
    }
}
