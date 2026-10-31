<?php

namespace App\Services\Finance;

use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\LedgerEntry;
use Illuminate\Support\Collection;

/**
 * The club's finance for one session, built from the ledger with no manual sorting: totals, a breakdown by
 * Membership, each Event and Fund withdrawals, and the filtered transaction list (also used for the AGM
 * Financial Report and the 02 Finance Record export).
 */
class FinanceReport
{
    public const SORTS = ['date_asc', 'date_desc', 'amount_desc', 'amount_asc'];

    public function __construct(private readonly Ledger $ledger) {}

    /**
     * @param  array{category?: string, event_id?: int, method?: string, search?: string, from?: string, to?: string, sort?: string}  $filters
     * @return array<string, mixed>
     */
    public function build(Club $club, AcademicSession $session, array $filters = []): array
    {
        $all = collect($this->ledger->rows($club))->where('category', '!=', 'opening');
        $from = max($session->starts_on->toDateString(), $filters['from'] ?? '0000-00-00');
        $to = min($session->ends_on->toDateString(), $filters['to'] ?? '9999-12-31');
        $inSession = $all->filter(fn (array $r) => $r['date'] >= $session->starts_on->toDateString() && $r['date'] <= $session->ends_on->toDateString());

        // Balance at the start of the session: opening balance plus everything recorded before it.
        $opening = (int) ($club->openingBalance->amount_sen ?? 0);
        $broughtForward = $opening + $all->filter(fn (array $r) => $r['date'] < $session->starts_on->toDateString())
            ->sum(fn (array $r) => $r['income_sen'] - $r['expense_sen']);

        $rows = $inSession->filter(fn (array $r) => $r['date'] >= $from && $r['date'] <= $to
            && (empty($filters['category']) || $r['category'] === $filters['category'])
            && (empty($filters['event_id']) || (int) $r['event_id'] === (int) $filters['event_id'])
            && (empty($filters['method']) || $r['method'] === $filters['method'])
            && (empty($filters['search']) || str_contains(mb_strtolower($r['description'].' '.$r['event'].' '.$r['handled_by']), mb_strtolower($filters['search']))));
        $rows = $this->sort($rows, $filters['sort'] ?? 'date_asc');

        return [
            'session' => $session->name,
            'brought_forward_sen' => $broughtForward,
            'closing_sen' => $broughtForward + $inSession->sum(fn (array $r) => $r['income_sen'] - $r['expense_sen']),
            'totals' => ['income_sen' => $inSession->sum('income_sen'), 'expense_sen' => $inSession->sum('expense_sen')],
            'by_category' => collect(LedgerEntry::CATEGORIES)->map(fn (string $label, string $key) => [
                'key' => $key, 'label' => $label,
                'income_sen' => $inSession->where('category', $key)->sum('income_sen'),
                'expense_sen' => $inSession->where('category', $key)->sum('expense_sen'),
                'count' => $inSession->where('category', $key)->count(),
            ])->values()->all(),
            'by_event' => $inSession->whereNotNull('event_id')->groupBy('event_id')->map(fn (Collection $g, $id) => [
                'event_id' => (int) $id, 'event' => $g->first()['event'],
                'income_sen' => $g->sum('income_sen'), 'expense_sen' => $g->sum('expense_sen'), 'count' => $g->count(),
            ])->sortBy('event')->values()->all(),
            'rows' => $rows->values()->all(),
            'filtered' => $this->isFiltered($filters),
        ];
    }

    /**
     * Rows for the official 02 Finance Record: a first line carrying the balance brought forward (or zero when a
     * filter shows only part of the year), then the transactions with the template's running balance.
     *
     * @param  array<string, mixed>  $report
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $report): array
    {
        $first = $report['filtered']
            ? ['date' => $report['rows'][0]['date'] ?? now()->toDateString(), 'description' => 'Filtered view (running total below)', 'income_sen' => 0, 'expense_sen' => 0, 'balance_sen' => 0]
            : ['date' => collect($report['rows'])->min('date') ?? now()->toDateString(), 'description' => "Balance brought forward ({$report['session']})", 'income_sen' => 0, 'expense_sen' => 0, 'balance_sen' => $report['brought_forward_sen']];

        return [$first, ...collect($report['rows'])->sortBy([['date', 'asc'], ['id', 'asc']])->values()->all()];
    }

    /** @param array<string, mixed> $filters */
    private function isFiltered(array $filters): bool
    {
        return collect($filters)->except('sort')->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sort(Collection $rows, string $sort): Collection
    {
        return match ($sort) {
            'date_desc' => $rows->sortBy([['date', 'desc'], ['id', 'desc']]),
            'amount_desc' => $rows->sortByDesc(fn (array $r) => max($r['income_sen'], $r['expense_sen'])),
            'amount_asc' => $rows->sortBy(fn (array $r) => max($r['income_sen'], $r['expense_sen'])),
            default => $rows->sortBy([['date', 'asc'], ['id', 'asc']]),
        };
    }
}
