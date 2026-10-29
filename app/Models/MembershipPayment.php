<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'membership_id', 'club_id', 'user_id', 'method', 'amount_sen', 'reference', 'receipt_path', 'ai_amount_sen', 'ai_date',
    'ai_reference', 'ai_payee', 'ai_confidence', 'flags', 'status', 'review_note', 'collected_by', 'verified_by', 'verified_at',
])]
#[Hidden(['receipt_path'])]
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
}
