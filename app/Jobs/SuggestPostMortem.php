<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Ai\PostMortemAdvisor;
use App\Services\Events\PostMortemForm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Fills only the empty improvement / recommendation fields, so nothing the committee wrote is overwritten. */
class SuggestPostMortem implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $documentId, public readonly int $userId) {}

    public static function statusKey(int $documentId): string
    {
        return "postmortem-suggest:{$documentId}";
    }

    public function handle(PostMortemAdvisor $advisor, PostMortemForm $form): void
    {
        $document = Document::with('event')->find($this->documentId);
        if (! $document || ! $document->status->isEditable()) {
            return;
        }
        $data = $form->current($document);
        $suggestions = $advisor->suggest($document->event->name, $data['rows']);
        if ($suggestions) {
            foreach ($data['rows'] as $key => $row) {
                if (trim($row['improvement']) === '' && isset($suggestions['improvements'][$key])) {
                    $data['rows'][$key]['improvement'] = $suggestions['improvements'][$key];
                }
            }
            $filled = array_values(array_filter($data['recommendations'], fn ($r) => trim((string) $r) !== ''));
            $data['recommendations'] = $filled ?: ($suggestions['recommendations'] ?: $data['recommendations']);
            $form->save($document, $data, $this->userId);
        }
        Cache::put(self::statusKey($this->documentId), $suggestions ? 'done' : 'failed', now()->addMinutes(10));
    }
}
