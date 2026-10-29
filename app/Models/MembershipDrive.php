<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A membership drive window set by SLD for one club, or all clubs when club_id is null. */
#[Fillable(['club_id', 'title', 'starts_at', 'ends_at', 'set_by'])]
class MembershipDrive extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @param Builder<MembershipDrive> $query */
    public function scopeRunning(Builder $query): void
    {
        $query->where('starts_at', '<=', now())->where('ends_at', '>=', now());
    }
}
