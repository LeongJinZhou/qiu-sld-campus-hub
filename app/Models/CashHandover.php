<?php

namespace App\Models;

use App\Enums\HandoverStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id', 'student_id', 'committee_user_id', 'status', 'qr_token_hash', 'photo_path', 'cash_check', 'accepted_at', 'declined_at',
    'student_ready_at', 'committee_ready_at', 'photo_taken_at', 'student_done_at', 'committee_done_at', 'completed_at', 'expires_at',
])]
#[Hidden(['qr_token_hash', 'photo_path'])]
class CashHandover extends Model
{
    public const LIFETIME_DAYS = 7;

    protected function casts(): array
    {
        $casts = ['status' => HandoverStatus::class, 'expires_at' => 'datetime'];
        foreach (['accepted', 'declined', 'student_ready', 'committee_ready', 'photo_taken', 'student_done', 'committee_done', 'completed'] as $step) {
            $casts["{$step}_at"] = 'datetime';
        }

        return $casts;
    }

    /** @return BelongsTo<MembershipPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(MembershipPayment::class, 'payment_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function committee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committee_user_id');
    }
}
