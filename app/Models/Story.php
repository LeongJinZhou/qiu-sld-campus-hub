<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** 24-hour story. */
#[Fillable(['club_id', 'author_id', 'media_path', 'expires_at'])]
class Story extends Model
{
    public const LIFETIME_HOURS = 24;

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** @param Builder<Story> $query */
    public function scopeLive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->media_path);
    }
}
