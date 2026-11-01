<?php

namespace App\Services\Social;

use App\Models\Club;
use App\Models\Post;
use App\Models\User;
use App\Notifications\SocialNotification;
use App\Services\Meetings\Meetings;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Tells a club's committee and advisor about likes, comments, saves and new followers, and a club's followers
 * about its new posts and events, unless they muted that type. Repeats on the same post are grouped into one unread notification ("Ali and 3 others liked…").
 */
class SocialNotifier
{
    private const VERBS = ['likes' => 'liked', 'comments' => 'commented on', 'saves' => 'saved', 'follows' => 'started following'];

    public function __construct(private readonly Meetings $meetings) {}

    /** Followers hear about a club's new post or newly approved event, unless they muted club posts. */
    public function tellFollowers(Post $post, ?User $author = null): void
    {
        $club = $post->club;
        if (! $club || $post->hidden_at !== null) {
            return;
        }
        $title = $post->type === Post::TYPE_EVENT && $post->event
            ? "{$club->name} has a new event: {$post->event->name}"
            : "{$club->name} shared a new ".($post->type === Post::TYPE_RECAP ? 'event recap' : 'post');
        $body = Str::limit((string) ($post->caption ?: $post->event?->name), 90);
        $club->followers()->when($author, fn ($q) => $q->whereKeyNot($author->id))->chunkById(200, function ($followers) use ($post, $title, $body): void {
            $to = $followers->reject(fn (User $u) => $u->hasMuted('club_posts'));
            Notification::send($to, new SocialNotification($title, $body, route('posts.show', $post), 'club_posts', "club_posts:post:{$post->id}"));
        }, 'users.id', 'id');
    }

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
