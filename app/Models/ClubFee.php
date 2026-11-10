<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fee per club per session, set by the Treasurer and approved by SLD. */
#[Fillable(['club_id', 'session_id', 'amount_sen', 'payment_url', 'account_name', 'payment_qr_paths', 'set_by', 'approved_by', 'approved_at'])]
class ClubFee extends Model
{
    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'payment_qr_paths' => 'array'];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<User, $this> the treasurer who proposed the fee */
    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }
}
