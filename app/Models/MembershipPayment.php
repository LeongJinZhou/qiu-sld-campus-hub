<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Services\Finance\OrderIds;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'order_id', 'membership_id', 'event_id', 'event_registration_id', 'club_id', 'user_id', 'method', 'amount_sen', 'reference', 'receipt_path', 'ai_amount_sen', 'ai_date',
    'ai_reference', 'ai_payee', 'ai_payer', 'reference_key', 'receipt_hash', 'ai_confidence', 'flags', 'status', 'review_note', 'collected_by', 'verified_by', 'verified_at',
])]
#[Hidden(['receipt_path', 'receipt_hash', 'reference_key'])]
class MembershipPayment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'ai_date' => 'date',
            'ai_confidence' => 'float',
            'flags' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<User, $this> */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    /** @return HasOne<CashHandover, $this> */
    public function handover(): HasOne
    {
        return $this->hasOne(CashHandover::class, 'payment_id');
    }

    /** Every payment gets a traceable order ID when it is created (membership or the event it is for). */
    protected static function booted(): void
    {
        static::creating(function (MembershipPayment $payment): void {
            $payment->order_id ??= app(OrderIds::class)->forStudent(
                Club::findOrFail($payment->club_id),
                $payment->event_id ? Event::findOrFail($payment->event_id) : null,
                User::findOrFail($payment->user_id),
                now(),
            );
        });
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<EventRegistration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    /** What the money is for, in words: "Membership" or the event name. */
    public function purpose(): string
    {
        return $this->event_id ? (string) $this->event?->name : 'Membership';
    }
}
