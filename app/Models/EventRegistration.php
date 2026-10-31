<?php

namespace App\Models;

use App\Services\Finance\OrderIds;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A sign-up for an event: "registered", or "pending_payment" until a paid event's fee is confirmed. A confirmed
 * place carries a ticket: a secret code for its QR and the order ID (the payment's, or its own for free events).
 */
#[Fillable(['event_id', 'user_id', 'status', 'paid_at', 'order_id'])]
#[Hidden(['ticket_code'])]
class EventRegistration extends Model
{
    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (EventRegistration $registration): void {
            if ($registration->status === 'registered') {
                $registration->ticket_code ??= Str::random(32);
                $registration->order_id ??= app(OrderIds::class)->forEvent($registration->event);
            }
        });
    }

    public function isPaidUp(): bool
    {
        return $this->status === 'registered';
    }

    /** Older confirmed places get their ticket the first time it is shown. */
    public function ensureTicket(): self
    {
        if ($this->isPaidUp() && ! $this->ticket_code) {
            $this->save();
        }

        return $this;
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
