<?php

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\ClubOpeningBalance;
use App\Models\LedgerEntry;
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

        return $balance;
    }

    public function isReady(Club $club): bool
    {
        return $club->openingBalance()->exists();
    }

    /** System income (membership fees). Recorded even before finance is opened so nothing is lost. */
    public function income(Club $club, CarbonInterface $date, string $description, int $sen, ?Model $source = null, ?User $by = null): LedgerEntry
    {
        return $club->ledger()->create([
            'entry_date' => $date, 'description' => $description, 'income_sen' => $sen,
            'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(), 'created_by' => $by?->id,
        ]);
    }

    /** Manual entries by the Treasurer. Every expense needs an original receipt. */
    public function manual(Club $club, CarbonInterface $date, string $description, int $incomeSen, int $expenseSen, ?UploadedFile $receipt, User $by): LedgerEntry
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

        $stored = $receipt ? Receipt::create([
            'club_id' => $club->id, 'path' => $receipt->store("receipts/{$club->id}", 'local'),
            'original_name' => $receipt->getClientOriginalName(), 'uploaded_by' => $by->id,
        ]) : null;
        $entry = $club->ledger()->create([
            'entry_date' => $date, 'description' => $description, 'income_sen' => $incomeSen, 'expense_sen' => $expenseSen,
            'receipt_id' => $stored?->id, 'created_by' => $by->id,
        ]);
        $this->audit->log('finance.entry', $club, ['entry_id' => $entry->id, 'income_sen' => $incomeSen, 'expense_sen' => $expenseSen]);

        return $entry;
    }

    /**
     * Opening line first, then entries by date with the running balance.
     *
     * @return list<array{id: ?int, date: string, description: string, income_sen: int, expense_sen: int, balance_sen: int, has_receipt: bool}>
     */
    public function rows(Club $club): array
    {
        $opening = $club->openingBalance;
        $balance = (int) ($opening->amount_sen ?? 0);
        $rows = [];
        if ($opening) {
            $rows[] = ['id' => null, 'date' => $opening->as_of->toDateString(), 'description' => 'Opening Acc', 'income_sen' => 0, 'expense_sen' => 0, 'balance_sen' => $balance, 'has_receipt' => false];
        }
        foreach ($club->ledger()->orderBy('entry_date')->orderBy('id')->get() as $entry) {
            $balance += $entry->income_sen - $entry->expense_sen;
            $rows[] = [
                'id' => $entry->id, 'date' => $entry->entry_date->toDateString(), 'description' => $entry->description,
                'income_sen' => $entry->income_sen, 'expense_sen' => $entry->expense_sen, 'balance_sen' => $balance,
                'has_receipt' => $entry->receipt_id !== null,
            ];
        }

        return $rows;
    }

    public function balance(Club $club): int
    {
        $rows = $this->rows($club);

        return $rows === [] ? 0 : end($rows)['balance_sen'];
    }
}
