<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['club_id', 'entry_date', 'description', 'income_sen', 'expense_sen', 'category', 'event_id', 'method', 'handled_by', 'source_type', 'source_id', 'receipt_id', 'created_by'])]
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
}
