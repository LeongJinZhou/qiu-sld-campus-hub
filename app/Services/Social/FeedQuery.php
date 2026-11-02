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

/**
 * Feed order: SLD-pinned first, then clubs the viewer follows, then newest. Only the last 7 days show in full;
 * older posts fold into "8–14 days ago", then one group per month this year, then one per earlier year.
 */
class FeedQuery
{
    public const FULL_DAYS = 7;

    public const BUCKET_LIMIT = 30;

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

        // Columns first: a select() after withCount()/withExists() would drop the like count and the viewer's state.
        return $this->posts->withCardData(Post::query()->visible()->select('posts.*'), $viewer)
            ->where(fn ($q) => $q->whereNotNull('pinned_at')->orWhere('published_at', '>=', now()->subDays(self::FULL_DAYS)))
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
     * Older posts folded into groups, newest group first: key, label, how many, and up to four thumbnails.
     *
     * @return list<array{key: string, label: string, count: int, thumbs: list<string>}>
     */
    public function archive(): array
    {
        $older = Post::query()->visible()->whereNull('pinned_at')->where('published_at', '<', now()->subDays(self::FULL_DAYS))
            ->with(['media' => fn ($q) => $q->where('sort', 0), 'event'])->latest('published_at')->get(['id', 'event_id', 'published_at']);

        return $older->groupBy(fn (Post $p) => $this->bucketOf($p->published_at))
            ->map(fn (Collection $posts, string $key) => [
                'key' => $key,
                'label' => $this->label($key),
                'count' => $posts->count(),
                'thumbs' => $posts->map(fn (Post $p) => $p->media->first()?->url() ?? $p->event?->posterUrl())->filter()->take(4)->values()->all(),
            ])->values()->all();
    }

    /** @return Collection<int, Post> one folded group, newest first */
    public function bucket(User $viewer, string $key): Collection
    {
        [$from, $to] = $this->range($key) ?? [null, null];
        if (! $from) {
            return collect();
        }

        return $this->posts->withCardData(Post::query()->visible(), $viewer)->whereNull('pinned_at')
            ->where('published_at', '>=', $from)->where('published_at', '<', $to)
            ->latest('published_at')->latest('id')->limit(self::BUCKET_LIMIT)->get();
    }

    private function bucketOf(Carbon $at): string
    {
        return match (true) {
            $at->gte(now()->subDays(14)) => '14d',
            $at->year === now()->year => 'm:'.$at->format('Y-m'),
            default => 'y:'.$at->year,
        };
    }

    private function label(string $key): string
    {
        return match (true) {
            $key === '14d' => '8–14 days ago',
            str_starts_with($key, 'm:') => Carbon::createFromFormat('Y-m', substr($key, 2))->format('F Y'),
            default => substr($key, 2),
        };
    }

    /** @return array{0: Carbon, 1: Carbon}|null published_at from (inclusive) and to (exclusive) for a group */
    private function range(string $key): ?array
    {
        $fullFrom = now()->subDays(self::FULL_DAYS);
        $fortnight = now()->subDays(14);
        if ($key === '14d') {
            return [$fortnight, $fullFrom];
        }
        if (preg_match('/^m:(\d{4})-(\d{2})$/', $key, $m) && (int) $m[1] === now()->year) {
            $start = Carbon::create((int) $m[1], (int) $m[2], 1)->startOfMonth();

            return [$start, $start->copy()->addMonth()->min($fortnight)];
        }
        if (preg_match('/^y:(\d{4})$/', $key, $m) && (int) $m[1] < now()->year) {
            $start = Carbon::create((int) $m[1], 1, 1)->startOfYear();

            return [$start, $start->copy()->addYear()];
        }

        return null;
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
