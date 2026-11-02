<?php

namespace App\Services\Finance;

use App\Models\LedgerEntry;
use App\Models\MembershipPayment;
use App\Support\SearchTokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

/**
 * SLD's transaction finder: any part of a transaction ID, or several parts in any order (club, event, student
 * ID, name, date, time, bank reference), matches student payments of every status and the club ledger. The club,
 * event and student are also matched by their own records, so a transaction is found even without its ID.
 */
class TransactionSearch
{
    public const LIMIT = 50;

    /** @return list<array<string, mixed>> */
    public function find(string $query): array
    {
        $tokens = SearchTokens::from($query);
        if ($tokens === [] || mb_strlen(implode('', $tokens)) < 2) {
            return [];
        }

        $payments = MembershipPayment::with(['club', 'event', 'user.student'])
            ->where(fn (Builder $q) => $this->matchAll($q, $tokens, ['reference', 'method'], ['club' => 'name', 'event' => 'name', 'user' => 'name', 'user.student' => 'student_number']))
            ->latest('id')->limit(self::LIMIT)->get()
            ->map(fn (MembershipPayment $p) => [
                'key' => "p{$p->id}", 'order_id' => $p->order_id, 'kind' => 'payment',
                'club' => $p->club->name, 'club_slug' => $p->club->slug, 'purpose' => $p->purpose(),
                'name' => $p->user->name, 'student_number' => $p->user->student?->student_number,
                'amount_sen' => $p->amount_sen, 'direction' => 'in', 'method' => $p->method, 'status' => $p->status->value,
                'reference' => $p->reference, 'at' => $p->created_at->toIso8601String(),
                'receipt_url' => $p->receipt_path ? URL::temporarySignedRoute('payments.receipt', now()->addMinutes(30), $p) : null,
            ]);

        // Ledger lines that are not a student payment (expenses, withdrawals, other income).
        $entries = LedgerEntry::with(['club', 'event'])
            ->where(fn (Builder $q) => $q->whereNull('source_type')->orWhere('source_type', '!=', (new MembershipPayment)->getMorphClass()))
            ->where(fn (Builder $q) => $this->matchAll($q, $tokens, ['description', 'category', 'method'], ['club' => 'name', 'event' => 'name']))
            ->latest('id')->limit(self::LIMIT)->get()
            ->map(fn (LedgerEntry $e) => [
                'key' => "l{$e->id}", 'order_id' => $e->order_id, 'kind' => $e->category ?? 'ledger',
                'club' => $e->club->name, 'club_slug' => $e->club->slug, 'purpose' => $e->event?->name ?? $e->description,
                'name' => null, 'student_number' => null,
                'amount_sen' => $e->income_sen ?: $e->expense_sen, 'direction' => $e->income_sen ? 'in' : 'out', 'method' => $e->method, 'status' => 'recorded',
                'reference' => null, 'at' => $e->entry_date->toDateString(), 'receipt_url' => null,
            ]);

        return $payments->concat($entries)->sortByDesc('at')->take(self::LIMIT)->values()->all();
    }

    /**
     * Every token must appear somewhere: in the ID, in one of the extra columns, or in a related record's column
     * (club, event, student name and number). Everything is compared upper case without spaces, _ and -.
     *
     * @param  list<string>  $tokens
     * @param  list<string>  $extra
     * @param  array<string, string>  $related  relation => column
     */
    private function matchAll(Builder $query, array $tokens, array $extra = [], array $related = []): void
    {
        foreach ($tokens as $token) {
            $like = '%'.addcslashes($token, '%_\\').'%';
            $query->where(function (Builder $q) use ($like, $extra, $related): void {
                $q->whereRaw("REPLACE(REPLACE(UPPER(order_id), '_', ''), '-', '') LIKE ?", [$like]);
                foreach ($extra as $column) {
                    $q->orWhereRaw($this->normalised($column).' LIKE ?', [$like]);
                }
                foreach ($related as $relation => $column) {
                    $q->orWhereHas($relation, fn (Builder $r) => $r->whereRaw($this->normalised($column).' LIKE ?', [$like]));
                }
            });
        }
    }

    private function normalised(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(UPPER({$column}), ' ', ''), '-', ''), '_', '')";
    }
}
