<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['club_id', 'user_id', 'status', 'starts_on', 'expires_on', 'grace_ends_on', 'alumni_since', 'acknowledged_at', 'last_reminded_at'])]
class Membership extends Model
{
    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'starts_on' => 'date',
            'expires_on' => 'date',
            'grace_ends_on' => 'date',
            'alumni_since' => 'date',
            'acknowledged_at' => 'datetime',
            'last_reminded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<MembershipPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(MembershipPayment::class);
    }
}
