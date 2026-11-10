<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Every notification also goes to the person's email (through the SMTP server in MAIL_*), in one branded template:
 * the title, the message, a button straight to the action (sign an approval, pay, open the record) and a link to
 * all notifications. The in-app copy is saved at once; the email is queued.
 */
trait SendsBrandedMail
{
    /** @return array<string, string> the in-app notice is written immediately; only the email waits for the queue */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /** First word of the name; a title ("Dr", "Ms") needs the next word too, or the email opens with "Hi Dr,". */
    private function greetingName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = $words[0] ?? 'there';
        if (isset($words[1]) && preg_match('/^(dr|mr|mrs|ms|miss|prof|ir|ts|dato|datuk|datin|puan|encik|cik)\.?$/i', $first)) {
            $first .= ' '.$words[1];
        }

        return $first;
    }

    protected function brandedMail(object $notifiable, string $title, string $body, string $url, ?string $category = null): MailMessage
    {
        $action = $this->actionLabel($url);

        return (new MailMessage)
            // One line only: a line break in a title (from a club or event name) can never add an email header.
            ->subject(mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $title)), 0, 180))
            ->view(['html' => 'emails.notification', 'text' => 'emails.notification-text'], [
                'name' => $this->greetingName((string) ($notifiable->name ?? '')),
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'action' => $action,
                'kind' => $this->kindLabel($url, $category),
                'notificationsUrl' => url('/notifications'),
                'settingsUrl' => url('/notifications?settings=1'),
                'mutable' => $category !== null,
            ]);
    }

    /** The button says what the link does. */
    private function actionLabel(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return match (true) {
            str_contains($path, '/approvals/') => 'Review and sign',
            str_contains($path, '/withdrawals/') => 'Open the fund withdrawal',
            str_contains($path, '/pay') => 'Pay now',
            str_contains($path, '/indemnit') => 'Sign the form',
            str_contains($path, '/meetings/') => 'Open the meeting',
            str_contains($path, '/events/') => 'View the event',
            str_contains($path, '/posts/') => 'View the post',
            str_contains($path, '/admin/') => 'Open in SLD Admin',
            str_contains($path, '/clubs/') => 'Open the club',
            default => 'Open Campus Hub',
        };
    }

    private function kindLabel(string $url, ?string $category): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return match (true) {
            str_contains($path, '/approvals/') => 'Approval needed',
            $category === 'club_posts' => 'From a club you follow',
            in_array($category, ['likes', 'comments', 'saves', 'follows'], true) => 'Club activity',
            default => 'Campus Hub update',
        };
    }
}
