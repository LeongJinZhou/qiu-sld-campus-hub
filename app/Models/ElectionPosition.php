<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['election_id', 'position', 'is_compulsory', 'sort', 'winner_candidate_id'])]
class ElectionPosition extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_compulsory' => 'boolean'];
    }

    /** @return BelongsTo<Election, $this> */
    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    /** @return HasMany<Candidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @return BelongsTo<Candidate, $this> */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'winner_candidate_id');
    }
}
