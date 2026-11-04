<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Club activity: likes, comments, saves and follows on a club the person runs, and new posts, events and activities
 * from clubs they follow. In the app and by email; a category switched off in Notifications → Settings stops both.
 */
class SocialNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsBrandedMail;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly string $category,
        public readonly string $key,
        public readonly int $count = 1,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (method_exists($notifiable, 'hasMuted') && $notifiable->hasMuted($this->category)) {
            return [];
        }

        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail($notifiable, $this->title, $this->body, $this->url, $this->category);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'category' => $this->category, 'key' => $this->key, 'count' => $this->count];
    }
}
