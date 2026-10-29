<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Fee per club per session, set by the Treasurer and approved by SLD. */
#[Fillable(['club_id', 'session_id', 'amount_sen', 'payment_url', 'account_name', 'set_by', 'approved_by', 'approved_at'])]
class ClubFee extends Model
{
    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }
}
