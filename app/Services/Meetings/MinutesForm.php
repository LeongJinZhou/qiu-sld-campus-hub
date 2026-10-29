<?php

namespace App\Services\Meetings;

use App\Models\Document;
use App\Models\Meeting;
use App\Services\Documents\Drafts;

/** The 13 Meeting Minutes fields, stored as JSON per document version. */
class MinutesForm
{
    public function __construct(private readonly Drafts $drafts) {}

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
        return $this->drafts->current($document, $this->defaults($document->meeting));
    }

    /** @param array<string, mixed> $data */
    public function saveDraft(Document $document, array $data, int $userId): void
    {
        $this->drafts->save($document, $data, $userId);
    }
}
