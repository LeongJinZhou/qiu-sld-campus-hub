<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'kind', 'source_id', 'title', 'statement', 'code', 'issued_at'])]
class Certificate extends Model
{
    public const KINDS = ['club_committee', 'event_committee', 'participation'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
