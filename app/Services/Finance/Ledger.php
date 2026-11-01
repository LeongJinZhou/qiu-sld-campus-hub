<?php

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubOpeningBalance;
use App\Models\Document;
use App\Models\LedgerEntry;
use App\Models\MembershipPayment;
use App\Models\Receipt;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/** Club ledger mirroring the 02 Finance Record: Date, Description, Income, Expenses, running Balance. */
class Ledger
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** SLD only; the first ledger line "Opening Acc". Changes are logged. */
    public function setOpeningBalance(Club $club, int $amountSen, CarbonInterface $asOf, ?string $note, User $by): ClubOpeningBalance
    {
        $before = $club->openingBalance?->only('amount_sen', 'as_of');
        $balance = ClubOpeningBalance::updateOrCreate(['club_id' => $club->id], [
            'amount_sen' => $amountSen, 'as_of' => $asOf, 'note' => $note, 'set_by' => $by->id,
        ]);
        $this->audit->log('finance.opening_balance', $club, ['before' => $before, 'after' => ['amount_sen' => $amountSen, 'as_of' => $asOf->toDateString()]]);
        $club->setRelation('openingBalance', $balance); // the lookup above cached "none"

        return $balance;
    }

    public function isReady(Club $club): bool
    {
        return $club->openingBalance()->exists();
    }

    /**
     * System income, filed automatically (membership fees: category, method and who received the cash).
     * Recorded even before finance is opened so nothing is lost.
     */
    public function income(Club $club, CarbonInterface $date, string $description, int $sen, ?Model $source = null, ?User $by = null,
        string $category = 'other', ?int $eventId = null, ?string $method = null, ?User $handledBy = null): LedgerEntry
    {
        return $club->ledger()->create([
            'entry_date' => $date, 'description' => $description, 'income_sen' => $sen,
            'category' => $category, 'event_id' => $eventId, 'method' => $method, 'handled_by' => $handledBy?->id,
            'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(), 'created_by' => $by?->id,
        ]);
    }

    /**
     * Manual entries by the Treasurer, filed under an event or "other". Every expense needs an original receipt.
     */
    public function manual(Club $club, CarbonInterface $date, string $description, int $incomeSen, int $expenseSen, ?UploadedFile $receipt, User $by,
        ?int $eventId = null, ?string $method = null): LedgerEntry
    {
        if (! $this->isReady($club)) {
            throw new DomainException('SLD must enter the opening balance before the ledger can be used.');
        }
        if (($incomeSen > 0) === ($expenseSen > 0)) {
            throw new DomainException('Enter either an income or an expense amount.');
        }
        if ($expenseSen > 0 && ! $receipt) {
            throw new DomainException('Every expense needs an original receipt.');
        }

        $entry = $club->ledger()->create([
            'entry_date' => $date, 'description' => $description, 'income_sen' => $incomeSen, 'expense_sen' => $expenseSen,
            'category' => $eventId ? 'event' : 'other', 'event_id' => $eventId, 'method' => $method, 'handled_by' => $by->id,
            'receipt_id' => $receipt ? $this->storeReceipt($club, $receipt, $by)->id : null, 'created_by' => $by->id,
        ]);
        $this->audit->log('finance.entry', $club, ['entry_id' => $entry->id, 'income_sen' => $incomeSen, 'expense_sen' => $expenseSen, 'event_id' => $eventId]);

        return $entry;
    }

    /** Receipts can be added to a transaction afterwards (e.g. the purchases paid by a fund withdrawal). */
    public function attachReceipt(LedgerEntry $entry, UploadedFile $receipt, User $by): void
    {
        $entry->update(['receipt_id' => $this->storeReceipt($entry->club, $receipt, $by)->id]);
        $this->audit->log('finance.receipt_attached', $entry->club, ['entry_id' => $entry->id]);
    }

    /**
     * Opening line first, then entries by date with the running balance, each with what it was for, how it was
     * paid, who handled it and its evidence.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(Club $club): array
    {
        $opening = $club->openingBalance;
        $balance = (int) ($opening->amount_sen ?? 0);
        $rows = [];
        if ($opening) {
            $rows[] = ['id' => null, 'date' => $opening->as_of->toDateString(), 'description' => 'Opening Acc', 'income_sen' => 0, 'expense_sen' => 0, 'balance_sen' => $balance,
                'category' => 'opening', 'order_id' => null, 'event_id' => null, 'event_slug' => null, 'event' => null, 'method' => null, 'handled_by' => null, 'has_receipt' => false, 'receipt_id' => null,
                'payment_id' => null, 'handover_id' => null, 'has_photo' => false, 'has_payment_receipt' => false, 'document_id' => null];
        }
        $entries = $club->ledger()->with(['event:id,name,slug', 'handler:id,name', 'source'])->orderBy('entry_date')->orderBy('id')->get();
        foreach ($entries as $entry) {
            // A fund withdrawal is an advance: club money moves from the fund to the committee's hands. It is not an
            // expense (the purchases it pays for are), so it never lowers the club's total.
            $transfer = $entry->category === 'withdrawal' ? $entry->expense_sen : 0;
            $balance += $entry->income_sen - ($entry->expense_sen - $transfer);
            $payment = $entry->source instanceof MembershipPayment ? $entry->source->loadMissing('handover') : null;
            $rows[] = [
                'id' => $entry->id, 'date' => $entry->entry_date->toDateString(),
                'description' => $entry->description.($transfer ? ' — advance of RM '.number_format($transfer / 100, 2).' to the committee (not an expense)' : ''),
                'income_sen' => $entry->income_sen, 'expense_sen' => $entry->expense_sen - $transfer, 'transfer_sen' => $transfer, 'balance_sen' => $balance,
                'category' => $entry->category, 'order_id' => $entry->order_id, 'event_id' => $entry->event_id, 'event_slug' => $entry->event?->slug, 'event' => $entry->event?->name,
                'method' => $entry->method, 'handled_by' => $entry->handler?->name,
                'has_receipt' => $entry->receipt_id !== null, 'receipt_id' => $entry->receipt_id,
                'payment_id' => $payment?->id, 'has_payment_receipt' => (bool) $payment?->receipt_path,
                'handover_id' => $payment?->handover?->id, 'has_photo' => (bool) $payment?->handover?->photo_path,
                'document_id' => $entry->source instanceof Document ? $entry->source->id : null,
            ];
        }

        return $rows;
    }

    /**
     * Where the club's money is: in the fund (held by QIU), or advanced to the committee and not yet spent. Club
     * money spent on an event (purchases, "club money") is taken from that event's advance first, then the fund.
     * Total assets = fund + outstanding advances.
     *
     * @return array{fund_sen: int, advances_sen: int, total_sen: int, by_event: list<array{event_id: ?int, event: ?string, advanced_sen: int, spent_sen: int, outstanding_sen: int}>}
     */
    public function position(Club $club): array
    {
        $total = $this->balance($club);
        $entries = $club->ledger()->with('event:id,name')->get();
        $byEvent = $entries->where('category', 'withdrawal')->groupBy('event_id')->map(function ($advances, $eventId) use ($entries): array {
            $advanced = (int) $advances->sum('expense_sen');
            // Spent from the advance: cash/club-money expenses of that event (or, for a club advance, other club costs).
            $spent = (int) $entries->where('category', $eventId ? 'event' : 'other')->where('event_id', $eventId ?: null)
                ->where('expense_sen', '>', 0)->where('method', '!=', 'online')->sum('expense_sen');
            $returned = (int) $entries->where('category', 'withdrawal')->where('event_id', $eventId)->sum('income_sen');

            return ['event_id' => $eventId ?: null, 'event' => $advances->first()->event?->name, 'advanced_sen' => $advanced,
                'spent_sen' => min($advanced, $spent), 'outstanding_sen' => max(0, $advanced - $spent - $returned)];
        })->values();
        $advances = (int) $byEvent->sum('outstanding_sen');

        return ['fund_sen' => $total - $advances, 'advances_sen' => $advances, 'total_sen' => $total, 'by_event' => $byEvent->all()];
    }

    public function balance(Club $club): int
    {
        $rows = $this->rows($club);

        return $rows === [] ? 0 : end($rows)['balance_sen'];
    }

    private function storeReceipt(Club $club, UploadedFile $receipt, User $by): Receipt
    {
        return Receipt::create([
            'club_id' => $club->id, 'path' => $receipt->store("receipts/{$club->id}", 'local'),
            'original_name' => $receipt->getClientOriginalName(), 'uploaded_by' => $by->id,
        ]);
    }
}
