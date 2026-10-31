<?php

namespace App\Services\Social;

use App\Models\Club;
use App\Models\User;
use App\Notifications\SocialNotification;
use App\Services\Meetings\Meetings;

/**
 * Tells a club's committee and advisor about likes, comments, saves and new followers, unless they muted that
 * type. Repeats on the same post are grouped into one unread notification ("Ali and 3 others liked…").
 */
class SocialNotifier
{
    private const VERBS = ['likes' => 'liked', 'comments' => 'commented on', 'saves' => 'saved', 'follows' => 'started following'];

    public function __construct(private readonly Meetings $meetings) {}

    public function notify(Club $club, User $actor, string $category, string $what, string $url, string $key): void
    {
        foreach ($this->meetings->committee($club) as $recipient) {
            if ($recipient->is($actor) || $recipient->hasMuted($category)) {
                continue;
            }
            $existing = $recipient->unreadNotifications()->where('data->key', $key)->first();
            $count = (int) ($existing?->data['count'] ?? 0) + 1;
            $who = $count > 1 ? "{$actor->name} and ".($count - 1).' '.($count - 1 === 1 ? 'other' : 'others') : $actor->name;
            $title = "{$who} ".self::VERBS[$category]." {$what}";
            $data = (new SocialNotification($title, $club->name, $url, $category, $key, $count))->toArray($recipient);
            if ($existing) {
                $existing->forceFill(['data' => $data, 'created_at' => now()])->save();
            } else {
                $recipient->notify(new SocialNotification($title, $club->name, $url, $category, $key, $count));
            }
        }
    }
}
