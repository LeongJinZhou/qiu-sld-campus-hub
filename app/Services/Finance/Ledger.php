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

    /** An approved fund withdrawal (07) leaves the club fund: filed under the event it was for, once only. */
    public function withdrawal(Document $document): ?LedgerEntry
    {
        $total = (int) ($document->data()['total_sen'] ?? 0);
        if ($total <= 0 || LedgerEntry::where('source_type', $document->getMorphClass())->where('source_id', $document->id)->exists()) {
            return null;
        }
        $document->loadMissing('club', 'event');

        return $document->club->ledger()->create([
            'entry_date' => today(), 'description' => 'Fund withdrawal (07)'.($document->event ? " – {$document->event->name}" : ''),
            'expense_sen' => $total, 'category' => 'withdrawal', 'event_id' => $document->event_id, 'method' => 'transfer',
            'source_type' => $document->getMorphClass(), 'source_id' => $document->id, 'created_by' => $document->created_by,
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
                'category' => 'opening', 'event_id' => null, 'event' => null, 'method' => null, 'handled_by' => null, 'has_receipt' => false, 'receipt_id' => null,
                'payment_id' => null, 'handover_id' => null, 'has_photo' => false, 'has_payment_receipt' => false, 'document_id' => null];
        }
        $entries = $club->ledger()->with(['event:id,name', 'handler:id,name', 'source'])->orderBy('entry_date')->orderBy('id')->get();
        foreach ($entries as $entry) {
            $balance += $entry->income_sen - $entry->expense_sen;
            $payment = $entry->source instanceof MembershipPayment ? $entry->source->loadMissing('handover') : null;
            $rows[] = [
                'id' => $entry->id, 'date' => $entry->entry_date->toDateString(), 'description' => $entry->description,
                'income_sen' => $entry->income_sen, 'expense_sen' => $entry->expense_sen, 'balance_sen' => $balance,
                'category' => $entry->category, 'event_id' => $entry->event_id, 'event' => $entry->event?->name,
                'method' => $entry->method, 'handled_by' => $entry->handler?->name,
                'has_receipt' => $entry->receipt_id !== null, 'receipt_id' => $entry->receipt_id,
                'payment_id' => $payment?->id, 'has_payment_receipt' => (bool) $payment?->receipt_path,
                'handover_id' => $payment?->handover?->id, 'has_photo' => (bool) $payment?->handover?->photo_path,
                'document_id' => $entry->source instanceof Document ? $entry->source->id : null,
            ];
        }

        return $rows;
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
