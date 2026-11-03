<?php

namespace App\Services\Events;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Events\DocumentApproved;
use App\Models\EventDocumentItem;
use App\Models\Post;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use App\Services\Social\SocialNotifier;
use App\Services\Time\SessionResolver;
use Illuminate\Support\Facades\Notification;

/**
 * On final proposal approval: feed post, the club committee, advisor and event committee told it is approved, CCA values locked in, document checklist,
 * registration and QR attendance unlocked (fund withdrawal checks approval status in Phase 3).
 */
class EventApprovalActions
{
    public function __construct(private readonly AuditLogger $audit, private readonly SessionResolver $sessions) {}

    public function handle(DocumentApproved $approved): void
    {
        $document = $approved->document;
        if ($document->type !== DocumentType::Proposal || ! $event = $document->event) {
            return;
        }

        $event->update([
            'status' => DocumentStatus::Approved,
            'approved_at' => now(),
            'registration_open' => true,
            'attendance_open' => true,
        ]);

        $post = Post::firstOrCreate(['event_id' => $event->id, 'type' => Post::TYPE_EVENT], [
            'club_id' => $event->club_id,
            'caption' => $event->summary,
            'published_at' => now(),
        ]);
        if ($post->wasRecentlyCreated) {
            app(SocialNotifier::class)->tellFollowers($post);
        }

        // The club's committee and advisor, the event committee and whoever submitted it: approved, and live on the feed.
        $people = User::whereIn('id', RoleAssignment::query()->actingFor($event->club_id, $this->sessions->current())->select('user_id'))
            ->orWhereIn('id', $event->committee()->select('user_id'))
            ->orWhere('id', $document->created_by)->get();
        Notification::send($people, new DocumentNotification("Event approved: {$event->name}",
            "{$event->club->name}: every signature is in. Registration and QR attendance are open, and it is announced on the feed.", route('posts.show', $post)));

        foreach (EventDocumentItem::KINDS as $kind => [, $days]) {
            $event->documentItems()->firstOrCreate(['kind' => $kind], ['due_on' => $event->ends_at->copy()->addDays($days)->toDateString()]);
        }

        $this->audit->log('event.approved', $event, ['cca_participant' => $event->cca_participant, 'cca_committee' => $event->cca_committee]);
    }
}
