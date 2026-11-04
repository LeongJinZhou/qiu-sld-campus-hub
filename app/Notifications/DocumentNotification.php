<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** In-app + email notice: approvals needed, returned, approved or rejected, payments, reminders and SLD requests. These cannot be switched off. */
class DocumentNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsBrandedMail;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail($notifiable, $this->title, $this->body, $this->url);
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
