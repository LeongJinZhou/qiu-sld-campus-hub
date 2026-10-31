<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One purchase for an event (a receipt), with its items, who bought it and how it was paid for. Money in sen. */
#[Fillable([
    'event_id', 'club_id', 'bought_by', 'status', 'paid_with', 'budget_line', 'shop', 'purchased_on', 'items', 'total_sen', 'receipt_path', 'receipt_hash',
    'ai_confidence', 'verifier_id', 'verified_at', 'review_note', 'settle_when', 'settle_method', 'paid_back_by', 'payout_path', 'payout_payee',
    'payout_reference', 'payout_check', 'received_at', 'ledger_entry_id',
])]
#[Hidden(['receipt_path', 'receipt_hash', 'payout_path'])]
class EventPurchase extends Model
{
    public const READING = 'reading';

    public const DRAFT = 'draft';

    public const PENDING = 'pending';

    public const OWED = 'owed';

    public const PAYING = 'paying';

    public const RECORDED = 'recorded';

    public const REJECTED = 'rejected';

    protected function casts(): array
    {
        return ['items' => 'array', 'purchased_on' => 'date', 'verified_at' => 'datetime', 'received_at' => 'datetime', 'ai_confidence' => 'float'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bought_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_back_by');
    }

    /** "Gloves ×40, Water ×30" */
    public function summary(): string
    {
        $items = collect($this->items ?? [])->map(fn (array $i) => $i['name'].((int) ($i['quantity'] ?? 1) > 1 ? ' ×'.$i['quantity'] : ''));

        return $items->take(4)->join(', ').($items->count() > 4 ? ' and '.($items->count() - 4).' more' : '');
    }
}
