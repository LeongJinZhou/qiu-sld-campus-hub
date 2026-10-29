<?php

namespace App\Services\Social;

use App\Models\Club;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Feed order: SLD-pinned first, then clubs the viewer follows, then newest (CLAUDE.md §6 Social). */
class FeedQuery
{
    public function __construct(private readonly PostPresenter $posts) {}

    public const STORIES_CACHE = 'feed:stories';

    /** Cached per user; cleared when they follow or unfollow a club. */
    public static function followedKey(int $userId): string
    {
        return "feed:followed:{$userId}";
    }

    /** @return Collection<int, int> */
    private function followed(User $viewer): Collection
    {
        return collect(Cache::remember(self::followedKey($viewer->id), now()->addHour(), fn () => $viewer->followedClubs()->pluck('clubs.id')->all()));
    }

    /** @return Paginator<int, Post> */
    public function feed(User $viewer, int $perPage = 10): Paginator
    {
        // Pages carry the viewer's own likes and saves, so only the inputs are cached, never the page.
        $followed = $this->followed($viewer);

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
        $followed = $this->followed($viewer)->all();
        // Everyone sees the same live stories; a minute of caching spares every feed load the query.
        $clubs = Cache::remember(self::STORIES_CACHE, now()->addMinute(), fn () => Club::query()
            ->whereHas('stories', fn ($q) => $q->live())
            ->with(['stories' => fn ($q) => $q->live()->orderBy('created_at')])
            ->get()
            ->map(fn (Club $c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name,
                'logo_url' => $c->logoUrl(),
                'stories' => $c->stories->map(fn (Story $s) => ['id' => $s->id, 'url' => $s->url(), 'created_at' => $s->created_at?->toIso8601String()])->all(),
            ])->all());

        return collect($clubs)
            ->sortByDesc(fn (array $c) => in_array($c['id'], $followed, true))
            ->values()
            ->map(fn (array $c) => [
                ...collect($c)->except('id')->all(),
                'stories' => array_map(fn ($s) => ['id' => $s['id'], 'url' => $s['url'], 'at' => $s['created_at'] ? Carbon::parse($s['created_at'])->diffForHumans() : null], $c['stories']),
            ]);
    }
}
