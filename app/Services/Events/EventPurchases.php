<?php

namespace App\Services\Events;

use App\Enums\ScopeType;
use App\Jobs\ReadPurchaseReceipt;
use App\Models\Event;
use App\Models\EventPurchase;
use App\Models\LedgerEntry;
use App\Models\Receipt;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Ai\CashPhotoChecker;
use App\Services\Ai\PurchaseReceiptReader;
use App\Services\Ai\ReceiptReader;
use App\Services\AuditLogger;
use App\Services\Finance\FundWithdrawals;
use App\Services\Membership\ReceiptVerifier;
use App\Services\QrCodes;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Purchase claims for an event. Whoever buys (event or club committee, or a student helper) snaps the receipt;
 * AI reads the items and prices, the buyer checks them and picks the budget line.
 *  - Paid with club money (committee only): goes straight into the ledger as an event expense.
 *  - Paid with their own money: an event committee member verifies it and decides to pay back now or after the
 *    event; the committee then pays back in cash (photo of the buyer's own QR with the cash, the buyer confirms)
 *    or by transfer (the transfer receipt must show the buyer's name and the right amount). Then it is recorded.
 */
class EventPurchases
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ReceiptVerifier $names,
        private readonly QrCodes $qr,
    ) {}

    public function canLog(User $user, Event $event): bool
    {
        return $user->can('organises', $event) || $event->helpers()->whereKey($user->id)->exists();
    }

    /**
     * Who may verify a claim: the event committee, or the club committee when the event has none.
     *
     * @return Collection<int, User>
     */
    public function verifiers(Event $event): Collection
    {
        $ids = RoleAssignment::where('scope_type', ScopeType::Event)->where('scope_id', $event->id)->pluck('user_id');
        if ($ids->isEmpty()) {
            $ids = $event->club->roleAssignments()->where('session_id', $event->session_id)->whereNotNull('position')->pluck('user_id');
        }

        return User::whereIn('id', $ids)->orderBy('name')->get();
    }

    /** Step 1: the receipt photo. Read in the queue; the same receipt cannot be claimed twice. */
    public function snap(Event $event, User $user, UploadedFile $receipt): EventPurchase
    {
        if (! $this->canLog($user, $event)) {
            throw new DomainException('Only the event committee, club committee and student helpers can log purchases.');
        }
        $hash = (string) hash_file('sha256', (string) $receipt->getRealPath());
        if (EventPurchase::where('receipt_hash', $hash)->where('status', '!=', EventPurchase::REJECTED)->exists()) {
            throw new DomainException('This receipt has already been logged.');
        }
        $purchase = EventPurchase::create([
            'event_id' => $event->id, 'club_id' => $event->club_id, 'bought_by' => $user->id, 'status' => EventPurchase::READING,
            'receipt_path' => $receipt->store("purchase-receipts/{$event->club_id}", 'local'), 'receipt_hash' => $hash,
        ]);
        ReadPurchaseReceipt::dispatch($purchase->id);

        return $purchase;
    }

    /** Runs in the queue. Unreadable receipts still become a draft the buyer fills in by hand. */
    public function read(EventPurchase $purchase, PurchaseReceiptReader $reader): void
    {
        if (EventPurchase::whereKey($purchase->id)->where('status', EventPurchase::READING)->update(['status' => EventPurchase::DRAFT]) === 0) {
            return;
        }
        $path = Storage::disk('local')->path($purchase->receipt_path);
        $reading = $reader->read($path, (string) (mime_content_type($path) ?: 'image/jpeg'));
        $items = $reading['items'] ?? [];
        $purchase->update([
            'items' => $items,
            'total_sen' => $reading['total_sen'] ?? array_sum(array_column($items, 'total_sen')),
            'shop' => $reading['shop'] ?? null,
            'purchased_on' => $reading['date'] ?? null,
            'ai_confidence' => $reading['confidence'] ?? null,
            'budget_line' => $this->guessBudgetLine($purchase->event, $items),
        ]);
    }

    /**
     * Step 2: the buyer confirms the items (edited if the AI misread) and how it was paid.
     *
     * @param  array{items: list<array{name: string, quantity: int, unit_sen: int}>, shop?: ?string, purchased_on?: ?string, budget_line?: ?string, paid_with: string, verifier_id?: ?int}  $data
     */
    public function submit(EventPurchase $purchase, User $user, array $data): void
    {
        if ($purchase->bought_by !== $user->id || $purchase->status !== EventPurchase::DRAFT) {
            throw new DomainException('This purchase is not waiting for your confirmation.');
        }
        $event = $purchase->event;
        $items = array_map(fn (array $i) => [
            'name' => trim($i['name']), 'quantity' => (int) $i['quantity'], 'unit_sen' => (int) $i['unit_sen'], 'total_sen' => (int) $i['quantity'] * (int) $i['unit_sen'],
        ], $data['items']);
        $total = array_sum(array_column($items, 'total_sen'));
        if ($total <= 0) {
            throw new DomainException('Add at least one item with a price.');
        }
        $byClub = $data['paid_with'] === 'club';
        if ($byClub && ! $user->can('organises', $event)) {
            throw new DomainException('Only the committee can spend club money. Choose "My own money" to claim it back.');
        }
        $verifier = null;
        if (! $byClub) {
            $verifier = $this->verifiers($event)->firstWhere('id', (int) ($data['verifier_id'] ?? 0));
            if (! $verifier || $verifier->is($user)) {
                throw new DomainException('Choose someone else on the event committee to verify your purchase.');
            }
        }
        $lines = collect(app(FundWithdrawals::class)->budgetLines($event))->pluck('description');
        $line = $data['budget_line'] ?? null;
        if ($line !== null && $line !== '' && ! $lines->contains($line)) {
            throw new DomainException('Pick a line from the approved budget, or "Not in the budget".');
        }

        $purchase->update([
            'items' => $items, 'total_sen' => $total, 'shop' => $data['shop'] ?? $purchase->shop, 'purchased_on' => $data['purchased_on'] ?? $purchase->purchased_on,
            'budget_line' => $line ?: null, 'paid_with' => $byClub ? 'club' : 'own', 'verifier_id' => $verifier?->id,
            'status' => $byClub ? EventPurchase::RECORDED : EventPurchase::PENDING,
        ]);
        $this->audit->log('purchase.submitted', $event, ['purchase_id' => $purchase->id, 'total_sen' => $total, 'paid_with' => $purchase->paid_with]);

        if ($byClub) {
            $this->record($purchase, $user, 'cash');

            return;
        }
        $verifier->notify(new DocumentNotification(
            "Verify a purchase for {$event->name}",
            "{$user->name} bought {$purchase->summary()} for RM ".number_format($total / 100, 2).' with their own money. Check the receipt and verify.',
            route('events.purchases', $event),
        ));
    }

    /** Step 3 (own money): the chosen event committee member checks the receipt; pay back now or after the event. */
    public function verify(EventPurchase $purchase, User $user, bool $approve, ?string $note, ?string $settleWhen): void
    {
        if ($purchase->status !== EventPurchase::PENDING || ($purchase->verifier_id !== $user->id && ! $user->isSldAdmin())) {
            throw new DomainException('This purchase is not waiting for your verification.');
        }
        if ($approve && ! in_array($settleWhen, ['now', 'after_event'], true)) {
            throw new DomainException('Choose when to pay it back: now or after the event.');
        }
        $purchase->update([
            'status' => $approve ? EventPurchase::OWED : EventPurchase::REJECTED, 'verified_at' => now(), 'review_note' => $note,
            'settle_when' => $approve ? $settleWhen : null,
        ]);
        $this->audit->log($approve ? 'purchase.verified' : 'purchase.rejected', $purchase->event, ['purchase_id' => $purchase->id]);
        $purchase->buyer->notify(new DocumentNotification(
            $approve ? 'Purchase verified' : 'Purchase not accepted',
            $approve
                ? "{$user->name} verified your RM ".number_format($purchase->total_sen / 100, 2).' purchase. You will be reimbursed '.($settleWhen === 'now' ? 'soon' : 'after the event').'.'
                : ($note ?: 'Please contact the event committee about this purchase.'),
            route('events.purchases', $purchase->event),
        ));
    }

    /** Pay back in cash: a photo of the buyer's own QR next to the cash; then the buyer confirms they got it. */
    public function payCash(EventPurchase $purchase, User $payer, UploadedFile $photo): void
    {
        $this->assertPayable($purchase, $payer);
        $token = $this->qr->profileToken((string) $photo->getRealPath());
        if ($token === null) {
            throw new DomainException("The photo does not show {$purchase->buyer->name}'s QR code clearly. Retake it with their phone and the cash in view.");
        }
        if (! hash_equals((string) $purchase->buyer->qr_token, $token)) {
            throw new DomainException("That QR code belongs to someone else. Scan {$purchase->buyer->name}'s own QR code.");
        }
        $cash = app(CashPhotoChecker::class)->check((string) $photo->getRealPath(), $purchase->total_sen);
        if ($cash !== null && ! $cash['cash_visible']) {
            throw new DomainException("No cash can be seen in the photo. Retake it with the cash next to {$purchase->buyer->name}'s phone.");
        }
        $purchase->update([
            'status' => EventPurchase::PAYING, 'settle_method' => 'cash', 'paid_back_by' => $payer->id,
            'payout_path' => $photo->store("purchase-payouts/{$purchase->club_id}", 'local'), 'payout_check' => 'ok',
        ]);
        $purchase->buyer->notify(new DocumentNotification(
            'Have you received your cash?',
            "{$payer->name} is paying you back RM ".number_format($purchase->total_sen / 100, 2).' in cash. Confirm once you have it.',
            route('events.purchases', $purchase->event),
        ));
    }

    public function confirmReceived(EventPurchase $purchase, User $buyer): void
    {
        if ($purchase->bought_by !== $buyer->id || $purchase->status !== EventPurchase::PAYING || $purchase->settle_method !== 'cash') {
            throw new DomainException('There is no cash payment waiting for your confirmation.');
        }
        $purchase->update(['received_at' => now()]);
        $this->record($purchase, $purchase->payer, 'cash');
    }

    /** Pay back by transfer (TNG, bank): the transfer receipt is read in the queue and must show the buyer's name. */
    public function payOnline(EventPurchase $purchase, User $payer, UploadedFile $transferReceipt): void
    {
        $this->assertPayable($purchase, $payer);
        $purchase->update([
            'status' => EventPurchase::PAYING, 'settle_method' => 'online', 'paid_back_by' => $payer->id, 'payout_check' => 'reading',
            'payout_path' => $transferReceipt->store("purchase-payouts/{$purchase->club_id}", 'local'),
        ]);
        ReadPurchaseReceipt::dispatch($purchase->id, payout: true);
    }

    /** Runs in the queue: the name on the transfer must be the buyer's and the amount the claim's. */
    public function readPayout(EventPurchase $purchase, ReceiptReader $reader): void
    {
        if ($purchase->status !== EventPurchase::PAYING || $purchase->payout_check !== 'reading') {
            return;
        }
        $path = Storage::disk('local')->path((string) $purchase->payout_path);
        $reading = $reader->read($path, (string) (mime_content_type($path) ?: 'image/jpeg'));
        if ($reading === null) {
            $purchase->update(['payout_check' => 'unread']);

            return;
        }
        $reference = $reading->reference ? strtoupper((string) preg_replace('/[^a-z0-9]/i', '', $reading->reference)) : null;
        $problems = array_filter([
            ! $this->names->namesMatch($reading->payee, $purchase->buyer->name) ? "the transfer went to \"{$reading->payee}\", not {$purchase->buyer->name}" : null,
            $reading->amountSen !== null && $reading->amountSen !== $purchase->total_sen ? 'the amount is RM '.number_format($reading->amountSen / 100, 2).', not RM '.number_format($purchase->total_sen / 100, 2) : null,
            $reference && EventPurchase::where('payout_reference', $reference)->whereKeyNot($purchase->id)->exists() ? 'this transfer was already used for another claim' : null,
        ]);
        $purchase->update(['payout_payee' => $reading->payee, 'payout_reference' => $reference]);
        if ($problems !== []) {
            $purchase->update(['status' => EventPurchase::OWED, 'payout_check' => 'mismatch', 'review_note' => 'Transfer not accepted: '.implode('; ', $problems).'.']);
            $purchase->payer?->notify(new DocumentNotification('Reimbursement transfer not accepted', (string) $purchase->review_note, route('events.purchases', $purchase->event)));

            return;
        }
        $purchase->update(['payout_check' => 'ok']);
        $this->record($purchase, $purchase->payer, 'online');
    }

    /** No AI available: the committee member who paid confirms the transfer went to the buyer. */
    public function confirmTransfer(EventPurchase $purchase, User $payer): void
    {
        if ($purchase->status !== EventPurchase::PAYING || $purchase->payout_check !== 'unread' || $purchase->paid_back_by !== $payer->id) {
            throw new DomainException('This transfer is not waiting for your confirmation.');
        }
        $this->record($purchase, $payer, 'online');
    }

    /**
     * Budget against spending, line by line, for the event's purchases page.
     *
     * @return list<array{description: string, budget_sen: int, spent_sen: int}>
     */
    public function budget(Event $event): array
    {
        $spent = EventPurchase::where('event_id', $event->id)->where('status', EventPurchase::RECORDED)->get()->groupBy(fn (EventPurchase $p) => $p->budget_line ?? '');
        $lines = array_map(fn (array $l) => ['description' => $l['description'], 'budget_sen' => $l['amount_sen'], 'spent_sen' => (int) ($spent[$l['description']] ?? collect())->sum('total_sen')],
            app(FundWithdrawals::class)->budgetLines($event));
        if (isset($spent[''])) {
            $lines[] = ['description' => 'Not in the budget', 'budget_sen' => 0, 'spent_sen' => (int) $spent['']->sum('total_sen')];
        }

        return $lines;
    }

    /** Into the club ledger as an event expense, with the shop receipt attached. Once only. */
    private function record(EventPurchase $purchase, ?User $handledBy, string $method): void
    {
        DB::transaction(function () use ($purchase, $handledBy, $method): void {
            $fresh = EventPurchase::whereKey($purchase->id)->lockForUpdate()->first();
            if ($fresh->ledger_entry_id !== null) {
                return;
            }
            $copy = "receipts/{$purchase->club_id}/".basename($purchase->receipt_path);
            Storage::disk('local')->copy($purchase->receipt_path, $copy);
            $receipt = Receipt::create(['club_id' => $purchase->club_id, 'path' => $copy, 'original_name' => 'Shop receipt.'.pathinfo($copy, PATHINFO_EXTENSION), 'uploaded_by' => $purchase->bought_by]);
            $buyer = $purchase->buyer;
            $entry = LedgerEntry::create([
                'club_id' => $purchase->club_id, 'entry_date' => $purchase->purchased_on ?? today(), 'event_id' => $purchase->event_id, 'category' => 'event',
                'description' => "Purchase: {$purchase->summary()}".($purchase->shop ? " ({$purchase->shop})" : '')." – bought by {$buyer->name}".($purchase->paid_with === 'own' ? ', paid back' : ''),
                'expense_sen' => $purchase->total_sen, 'method' => $method, 'handled_by' => $handledBy?->id, 'receipt_id' => $receipt->id,
                'source_type' => $purchase->getMorphClass(), 'source_id' => $purchase->id, 'created_by' => $handledBy?->id ?? $buyer->id,
            ]);
            $purchase->update(['status' => EventPurchase::RECORDED, 'ledger_entry_id' => $entry->id]);
        });
        $this->audit->log('purchase.recorded', $purchase->event, ['purchase_id' => $purchase->id, 'total_sen' => $purchase->total_sen]);
        if ($purchase->paid_with === 'own') {
            $purchase->buyer->notify(new DocumentNotification('Paid back', 'RM '.number_format($purchase->total_sen / 100, 2)." for {$purchase->summary()} is settled and recorded in the finance records of the club.", route('events.purchases', $purchase->event)));
        }
    }

    private function assertPayable(EventPurchase $purchase, User $payer): void
    {
        if ($purchase->status !== EventPurchase::OWED) {
            throw new DomainException('This purchase is not waiting to be paid back.');
        }
        if (! $payer->can('organises', $purchase->event) || $payer->is($purchase->buyer)) {
            throw new DomainException('Another committee member must pay the buyer back.');
        }
        if ($purchase->settle_when === 'after_event' && $purchase->event->ends_at->isFuture()) {
            throw new DomainException('This purchase will be paid back after the event ends.');
        }
    }

    /** @param list<array{name: string}> $items the budget line whose words best match the items, if any */
    private function guessBudgetLine(Event $event, array $items): ?string
    {
        $text = mb_strtolower(implode(' ', array_column($items, 'name')));
        $best = null;
        $score = 0;
        foreach (app(FundWithdrawals::class)->budgetLines($event) as $line) {
            $words = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($line['description'])) ?: [], fn ($w) => mb_strlen($w) >= 3);
            $hits = count(array_filter($words, fn ($w) => str_contains($text, rtrim($w, 's'))));
            if ($hits > $score) {
                [$best, $score] = [$line['description'], $hits];
            }
        }

        return $best;
    }
}
