<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A sign-up for an event: "registered", or "pending_payment" until a paid event's fee is confirmed. */
#[Fillable(['event_id', 'user_id', 'status', 'paid_at'])]
class EventRegistration extends Model
{
    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function isPaidUp(): bool
    {
        return $this->status === 'registered';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<MembershipPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(MembershipPayment::class);
    }
}
