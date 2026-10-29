<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['agm_id', 'term_session_id', 'allow_alumni_vote', 'nominations_open_at', 'nominations_close_at', 'voting_open_at', 'voting_close_at', 'results_published_at', 'handed_over_at'])]
class Election extends Model
{
    protected function casts(): array
    {
        return [
            'allow_alumni_vote' => 'boolean',
            'nominations_open_at' => 'datetime', 'nominations_close_at' => 'datetime',
            'voting_open_at' => 'datetime', 'voting_close_at' => 'datetime',
            'results_published_at' => 'datetime', 'handed_over_at' => 'datetime',
        ];
    }

    /** @return 'upcoming'|'nominating'|'between'|'voting'|'closed'|'published' */
    public function phase(): string
    {
        $now = now();

        return match (true) {
            $this->results_published_at !== null => 'published',
            $now->lt($this->nominations_open_at) => 'upcoming',
            $now->lte($this->nominations_close_at) => 'nominating',
            $now->lt($this->voting_open_at) => 'between',
            $now->lte($this->voting_close_at) => 'voting',
            default => 'closed',
        };
    }

    /** @return BelongsTo<Agm, $this> */
    public function agm(): BelongsTo
    {
        return $this->belongsTo(Agm::class);
    }

    /** @return BelongsTo<AcademicSession, $this> */
    public function termSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'term_session_id');
    }

    /** @return HasMany<ElectionPosition, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(ElectionPosition::class)->orderBy('sort');
    }
}
