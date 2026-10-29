<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['election_position_id', 'user_id', 'statement', 'photo_path'])]
class Candidate extends Model
{
    /** @return BelongsTo<ElectionPosition, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(ElectionPosition::class, 'election_position_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
