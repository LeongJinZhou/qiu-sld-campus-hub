<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['club_id', 'created_by', 'subject', 'body', 'due_on', 'reply', 'replied_by', 'replied_at'])]
class SldRequest extends Model
{
    public const REPLY_WORKING_DAYS = 7;

    protected function casts(): array
    {
        return ['due_on' => 'date', 'replied_at' => 'datetime'];
    }

    public function isOverdue(): bool
    {
        return $this->replied_at === null && $this->due_on->lt(today());
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<User, $this> */
    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
