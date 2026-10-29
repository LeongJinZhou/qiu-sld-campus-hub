<?php

namespace App\Services\Social;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Shapes posts for the feed and club pages, with the viewer's like/save state. */
class PostPresenter
{
    /** @param Builder<Post> $query */
    public function withCardData(Builder $query, User $viewer): Builder
    {
        return $query
            ->with(['club', 'event', 'media', 'comments' => fn ($q) => $q->whereNull('hidden_at')->with('user')->latest()->limit(2)])
            ->withCount(['likers as likes_count', 'comments as comments_count' => fn ($q) => $q->whereNull('hidden_at')])
            ->withExists([
                'likers as liked' => fn ($q) => $q->where('users.id', $viewer->id),
                'savers as saved' => fn ($q) => $q->where('users.id', $viewer->id),
            ]);
    }

    /** @return array<string, mixed> */
    public function card(Post $post): array
    {
        $event = $post->event;

        return [
            'id' => $post->id,
            'type' => $post->type,
            'caption' => $post->caption,
            'pinned' => $post->pinned_at !== null,
            'published_at' => $post->published_at->diffForHumans(),
            'club' => $post->club ? ['slug' => $post->club->slug, 'name' => $post->club->name, 'logo_url' => $post->club->logoUrl()] : null,
            'media' => $post->media->map(fn ($m) => $m->url())->all() ?: array_filter([$event?->posterUrl()]),
            'event' => $event ? [
                'slug' => $event->slug,
                'name' => $event->name,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at->toIso8601String(),
                'venue' => $event->venue,
                'registration_open' => $event->registration_open,
            ] : null,
            'likes_count' => $post->likes_count ?? 0,
            'comments_count' => $post->comments_count ?? 0,
            'liked' => (bool) ($post->liked ?? false),
            'saved' => (bool) ($post->saved ?? false),
            'comments' => $post->comments->reverse()->values()->map(fn (Comment $c) => $this->comment($c))->all(),
            'url' => route('posts.show', $post),
        ];
    }

    /** @return array<string, mixed> */
    public function comment(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'user' => $comment->user->name,
            'photo_url' => $comment->user->photoUrl(),
            'at' => $comment->created_at?->diffForHumans(),
        ];
    }
}
