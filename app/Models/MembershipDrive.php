<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A membership drive window set by SLD for one club, or all clubs when club_id is null. */
#[Fillable(['club_id', 'title', 'starts_at', 'ends_at', 'set_by'])]
class MembershipDrive extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @param Builder<MembershipDrive> $query */
    public function scopeRunning(Builder $query): void
    {
        $query->where('starts_at', '<=', now())->where('ends_at', '>=', now());
    }
}
