<?php

namespace App\Services\Meetings;

use App\Models\Document;
use App\Models\Meeting;

/** The 13 Meeting Minutes fields, stored as JSON per document version. */
class MinutesForm
{
    /** @return array<string, mixed> */
    public function defaults(Meeting $meeting): array
    {
        return [
            'purpose' => $meeting->title,
            'summary' => [''],
            'actions' => [],
            'next_date' => '', 'next_time' => '', 'next_venue' => '',
            'notes' => '',
        ];
    }

    /** @return array<string, mixed> draft (version 0) if any, else the latest submitted data, else defaults */
    public function current(Document $document): array
    {
        $draft = $document->versions()->where('number', 0)->first()?->data;

        return array_replace($this->defaults($document->meeting), $draft ?? $document->data());
    }

    /** @param array<string, mixed> $data */
    public function saveDraft(Document $document, array $data, int $userId): void
    {
        $document->versions()->updateOrCreate(['number' => 0], ['data' => $data, 'created_by' => $userId]);
    }
}
