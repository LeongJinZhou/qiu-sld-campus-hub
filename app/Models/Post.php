<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['club_id', 'event_id', 'author_id', 'type', 'caption', 'pinned_at', 'hidden_at', 'published_at'])]
class Post extends Model
{
    public const TYPE_EVENT = 'event';

    public const TYPE_RECAP = 'recap';

    public const TYPE_GENERAL = 'general';

    protected function casts(): array
    {
        return ['pinned_at' => 'datetime', 'hidden_at' => 'datetime', 'published_at' => 'datetime'];
    }

    /** @param Builder<Post> $query */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('hidden_at')->where('published_at', '<=', now());
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<PostMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('sort');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<PostReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(PostReport::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes');
    }

    /** @return BelongsToMany<User, $this> */
    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saves');
    }
}
