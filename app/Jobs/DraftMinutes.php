<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Ai\MinutesDrafter;
use App\Services\Meetings\MinutesForm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** AI drafting runs in the queue; the minutes page polls until the draft lands. */
class DraftMinutes implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $documentId, public readonly int $userId) {}

    public static function statusKey(int $documentId): string
    {
        return "minutes-draft:{$documentId}";
    }

    public function handle(MinutesDrafter $drafter, MinutesForm $form): void
    {
        $document = Document::with('meeting.club')->find($this->documentId);
        if (! $document || ! $document->status->isEditable()) {
            Cache::forget(self::statusKey($this->documentId));

            return;
        }
        $current = $form->current($document);
        $draft = $drafter->draft($document->meeting->club->name, $document->meeting->title, (string) $current['notes']);
        if ($draft) {
            $form->saveDraft($document, [
                ...$current,
                'purpose' => $draft['purpose'],
                'summary' => $draft['summary'] ?: [''],
                'actions' => $draft['actions'],
            ], $this->userId);
        }
        Cache::put(self::statusKey($this->documentId), $draft ? 'done' : 'failed', now()->addMinutes(10));
    }
}
