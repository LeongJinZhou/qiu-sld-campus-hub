<?php

namespace App\Services\Social;

use App\Models\Club;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

/** Feed order: SLD-pinned first, then clubs the viewer follows, then newest (CLAUDE.md §6 Social). */
class FeedQuery
{
    public function __construct(private readonly PostPresenter $posts) {}

    /** @return Paginator<int, Post> */
    public function feed(User $viewer, int $perPage = 10): Paginator
    {
        $followed = $viewer->followedClubs()->pluck('clubs.id');

        return $this->posts->withCardData(Post::query()->visible(), $viewer)
            ->select('posts.*')
            ->selectRaw('CASE WHEN pinned_at IS NULL THEN 0 ELSE 1 END AS is_pinned')
            ->selectRaw(
                $followed->isEmpty() ? '0 AS is_followed' : 'CASE WHEN club_id IN ('.implode(',', array_fill(0, $followed->count(), '?')).') THEN 1 ELSE 0 END AS is_followed',
                $followed->all(),
            )
            ->orderByDesc('is_pinned')
            ->orderByDesc('is_followed')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->simplePaginate($perPage);
    }

    /**
     * Clubs with live stories, followed clubs first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function storyBar(User $viewer): Collection
    {
        $followed = $viewer->followedClubs()->pluck('clubs.id')->all();

        return Club::query()
            ->whereHas('stories', fn ($q) => $q->live())
            ->with(['stories' => fn ($q) => $q->live()->orderBy('created_at')])
            ->get()
            ->sortByDesc(fn (Club $c) => in_array($c->id, $followed, true))
            ->values()
            ->map(fn (Club $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'logo_url' => $c->logoUrl(),
                'stories' => $c->stories->map(fn (Story $s) => ['id' => $s->id, 'url' => $s->url(), 'at' => $s->created_at?->diffForHumans()])->all(),
            ]);
    }
}
