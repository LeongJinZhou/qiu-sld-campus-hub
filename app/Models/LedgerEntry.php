<?php

namespace App\Models;

use App\Services\Finance\OrderIds;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['order_id', 'club_id', 'entry_date', 'description', 'income_sen', 'expense_sen', 'category', 'event_id', 'method', 'handled_by', 'source_type', 'source_id', 'receipt_id', 'created_by'])]
class LedgerEntry extends Model
{
    /** What a transaction is for. Filed automatically for system entries; chosen for manual ones. */
    public const CATEGORIES = ['membership' => 'Membership', 'event' => 'Event', 'withdrawal' => 'Fund withdrawal', 'other' => 'Other'];

    public const METHODS = ['online' => 'Online transfer', 'cash' => 'Cash', 'transfer' => 'Bank transfer (QIU)', 'other' => 'Other'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'income_sen' => 'integer', 'expense_sen' => 'integer'];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Same order ID as the payment it records; otherwise one under its event, or WITHDRAWAL / GENERAL. */
    protected static function booted(): void
    {
        static::creating(function (LedgerEntry $entry): void {
            if ($entry->order_id) {
                return;
            }
            if ($entry->source_type === (new MembershipPayment)->getMorphClass() && $entry->source_id) {
                $entry->order_id = MembershipPayment::whereKey($entry->source_id)->value('order_id');
            }
            $ids = app(OrderIds::class);
            $entry->order_id ??= $entry->event_id
                ? $ids->forEvent(Event::with('club')->findOrFail($entry->event_id))
                : $ids->forClub(Club::findOrFail($entry->club_id), $entry->category === 'withdrawal' ? 'withdrawal' : 'general');
        });
    }
}
