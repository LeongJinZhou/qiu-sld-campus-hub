<?php

namespace App\Services\Documents;

use App\Models\Document;

/** Working copy of a form: version 0 is the draft, numbered versions are what was submitted. */
class Drafts
{
    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public function current(Document $document, array $defaults): array
    {
        $draft = $document->versions()->where('number', 0)->first()?->data;

        return array_replace($defaults, $draft ?? $document->data());
    }

    /** @param array<string, mixed> $data */
    public function save(Document $document, array $data, int $userId): void
    {
        $document->versions()->updateOrCreate(['number' => 0], ['data' => $data, 'created_by' => $userId]);
    }
}
