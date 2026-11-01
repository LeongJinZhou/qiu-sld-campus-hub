<?php

namespace App\Services\Social;

use App\Enums\DocumentStatus;
use App\Models\Club;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\ContentFilter;

/**
 * Keeps the feed fresh and civil. Committees: at most 5 posts a club a day, one post per event a day and 3 per
 * event in all (the automatic event post aside), and no repeat of a recent caption or of an event already posted
 * about today. Everyone: no bad words, no comment floods, no copy-paste comments.
 */
class PostingRules
{
    public const CLUB_DAILY = 5;

    public const PER_EVENT_TOTAL = 3;

    public const COMMENTS_PER_MINUTE = 5;

    /** @return array<string, string> field => message; empty when the post may go up */
    public function postProblems(Club $club, ?int $eventId, ?string $caption): array
    {
        if ($words = ContentFilter::offences($caption)) {
            return ['caption' => $this->rudeMessage($words)];
        }
        $own = Post::where('club_id', $club->id)->where('type', '!=', Post::TYPE_EVENT);
        if ((clone $own)->where('created_at', '>=', now()->subDay())->count() >= self::CLUB_DAILY) {
            return ['caption' => 'Your club has posted '.self::CLUB_DAILY.' times in the last 24 hours. Give the feed a rest and post again tomorrow.'];
        }
        if ($eventId) {
            $forEvent = (clone $own)->where('event_id', $eventId);
            if ((clone $forEvent)->where('created_at', '>=', now()->subDay())->exists()) {
                return ['event_id' => 'You already posted about this event in the last 24 hours. Add photos to that post instead of posting again.'];
            }
            if ($forEvent->count() >= self::PER_EVENT_TOTAL) {
                return ['event_id' => 'This event already has '.self::PER_EVENT_TOTAL.' posts. Put more photos in the gallery instead.'];
            }
        }
        $text = $this->normalise($caption);
        if ($text !== '') {
            $recent = (clone $own)->where('created_at', '>=', now()->subDays(7))->whereNotNull('caption')->pluck('caption');
            if ($recent->contains(fn (string $c) => $this->similar($this->normalise($c), $text))) {
                return ['caption' => 'This is the same as a post your club made this week. Write something new.'];
            }
            if (! $eventId && ($name = $this->eventPostedToday($club, $text))) {
                return ['caption' => "Your club already posted about {$name} today. One post per event a day keeps the feed fresh."];
            }
        }

        return [];
    }

    public function commentProblem(User $user, Post $post, string $body): ?string
    {
        if ($words = ContentFilter::offences($body)) {
            return $this->rudeMessage($words);
        }
        if (Comment::where('user_id', $user->id)->where('created_at', '>=', now()->subMinute())->count() >= self::COMMENTS_PER_MINUTE) {
            return 'You are commenting too fast. Wait a minute and try again.';
        }
        $text = $this->normalise($body);
        $same = Comment::where('user_id', $user->id)->where('post_id', $post->id)->where('created_at', '>=', now()->subHour())->pluck('body')
            ->contains(fn (string $c) => $this->normalise($c) === $text);

        return $same ? 'You already posted this comment.' : null;
    }

    /** @param list<string> $words */
    private function rudeMessage(array $words): string
    {
        return 'Please keep it respectful: remove "'.implode('", "', array_slice($words, 0, 3)).'". Posts and comments with bad words or insults are not allowed.';
    }

    /** Name of an upcoming or recent club event that was posted about in the last 24 hours and that this caption names. */
    private function eventPostedToday(Club $club, string $text): ?string
    {
        $events = $club->events()->where('status', DocumentStatus::Approved)->where('ends_at', '>=', now()->subDays(14))
            ->whereHas('posts', fn ($q) => $q->where('type', '!=', Post::TYPE_EVENT)->where('created_at', '>=', now()->subDay()))->pluck('name');

        return $events->first(fn (string $name) => mb_strlen($this->normalise($name)) >= 4 && str_contains($text, $this->normalise($name)));
    }

    private function normalise(?string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $text))));
    }

    /** Same words with small edits (a changed emoji, one extra word) still count as a repeat. */
    private function similar(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (min(mb_strlen($a), mb_strlen($b)) < 12) {
            return false;
        }
        similar_text($a, $b, $percent);

        return $percent >= 85;
    }
}
