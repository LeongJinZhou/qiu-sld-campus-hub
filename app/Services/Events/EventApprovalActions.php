<?php

namespace App\Services\Events;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Events\DocumentApproved;
use App\Models\EventDocumentItem;
use App\Models\Post;
use App\Services\AuditLogger;

/**
 * On final proposal approval: feed post, CCA values locked in, document checklist,
 * registration and QR attendance unlocked (fund withdrawal checks approval status in Phase 3).
 */
class EventApprovalActions
{
    public function __construct(private readonly AuditLogger $audit) {}

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

        Post::firstOrCreate(['event_id' => $event->id, 'type' => Post::TYPE_EVENT], [
            'club_id' => $event->club_id,
            'caption' => $event->summary,
            'published_at' => now(),
        ]);

        foreach (EventDocumentItem::KINDS as $kind => [, $days]) {
            $event->documentItems()->firstOrCreate(['kind' => $kind], ['due_on' => $event->ends_at->copy()->addDays($days)->toDateString()]);
        }

        $this->audit->log('event.approved', $event, ['cca_participant' => $event->cca_participant, 'cca_committee' => $event->cca_committee]);
    }
}
