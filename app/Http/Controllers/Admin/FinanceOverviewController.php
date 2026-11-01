<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Services\Finance\Ledger;
use App\Services\Finance\TransactionSearch;
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
