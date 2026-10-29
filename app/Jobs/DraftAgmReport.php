<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Agm\AgmReportForm;
use App\Services\Ai\AgmDrafter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** AI drafts the AGM objectives, opening speech and agenda in the queue; only empty parts are filled. */
class DraftAgmReport implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $documentId, public readonly int $userId) {}

    public static function statusKey(int $documentId): string
    {
        return "agm-draft:{$documentId}";
    }

    public function handle(AgmDrafter $drafter, AgmReportForm $form): void
    {
        $document = Document::with('agm.club', 'agm.session')->find($this->documentId);
        if (! $document || ! $document->status->isEditable()) {
            Cache::forget(self::statusKey($this->documentId));

            return;
        }
        $draft = $drafter->draft($document->agm);
        if ($draft) {
            $current = $form->current($document);
            $empty = fn (array $list) => array_filter($list, fn ($v) => trim(is_array($v) ? (string) ($v['item'] ?? '') : (string) $v) !== '') === [];
            $form->saveDraft($document, [
                ...$current,
                'objectives' => $empty($current['objectives']) ? $draft['objectives'] : $current['objectives'],
                'speech' => $empty($current['speech']) ? $draft['speech'] : $current['speech'],
                'agenda' => count($current['agenda']) <= 1 ? $draft['agenda'] : $current['agenda'],
            ], $this->userId);
        }
        Cache::put(self::statusKey($this->documentId), $draft ? 'done' : 'failed', now()->addMinutes(10));
    }
}
