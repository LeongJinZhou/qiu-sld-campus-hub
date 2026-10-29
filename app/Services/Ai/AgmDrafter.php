<?php

namespace App\Services\Ai;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Agm;
use App\Models\Document;

/** Drafts the AGM Report's objectives (3), opening speech and agenda from the whole session's records. */
class AgmDrafter
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'objectives' => ['type' => 'array', 'items' => ['type' => 'string']],
            'speech' => ['type' => 'array', 'items' => ['type' => 'string']],
            'agenda' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => ['start' => ['type' => 'string'], 'end' => ['type' => 'string'], 'item' => ['type' => 'string']],
                'required' => ['start', 'end', 'item'],
                'additionalProperties' => false,
            ]],
        ],
        'required' => ['objectives', 'speech', 'agenda'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly AiClient $ai) {}

    /** @return array{objectives: list<string>, speech: list<string>, agenda: list<array{start: string, end: string, item: string}>}|null */
    public function draft(Agm $agm): ?array
    {
        $context = $this->sessionRecords($agm);
        $prompt = <<<TXT
        You are helping the secretary of a university student club ({$agm->club->name}) write its Annual General
        Meeting report for session {$agm->session->name}. The AGM runs {$agm->starts_at->format('g.i A')} to {$agm->ends_at->format('g.i A')}.
        From the club's records below, write in plain, formal English:
        - objectives: exactly 3 objectives of this AGM, each one sentence, grounded in what the club did this session.
        - speech: 2 or 3 points for the President's opening speech (thanks, highlights of the session, the year ahead).
        - agenda: the AGM agenda from start to end time, times like "4.00PM", covering arrival and attendance,
          opening speech, session review, financial report, election of the new committee, appointment, adjournment.
        Do not invent events, figures or names that are not in the records.

        Records:
        {$context}
        TXT;

        $data = $this->ai->json($prompt, self::SCHEMA);

        return $data ? ['objectives' => array_slice($data['objectives'], 0, 3), 'speech' => $data['speech'], 'agenda' => $data['agenda']] : null;
    }

    /** Event names, dates and summaries, minutes points and post-mortem recommendations; no personal data. */
    private function sessionRecords(Agm $agm): string
    {
        $documents = Document::with(['latestVersion', 'event', 'meeting'])->where('club_id', $agm->club_id)->where('session_id', $agm->session_id)
            ->whereIn('type', [DocumentType::Proposal, DocumentType::Minutes, DocumentType::PostMortem])
            ->where('status', '!=', DocumentStatus::Draft)->get();
        $lines = [];
        foreach ($documents as $doc) {
            $d = $doc->latestVersion->data ?? [];
            $lines[] = match ($doc->type) {
                DocumentType::Proposal => "Event: {$doc->event?->name} on {$doc->event?->starts_at?->format('j M Y')}. ".($d['summary'] ?? ''),
                DocumentType::Minutes => "Meeting {$doc->meeting?->starts_at?->format('j M Y')}: ".implode('; ', array_filter($d['summary'] ?? [])),
                default => "Post mortem of {$doc->event?->name}: ".implode('; ', array_filter($d['recommendations'] ?? [])),
            };
        }

        return $lines ? implode("\n", array_map(fn ($l) => '- '.mb_substr($l, 0, 600), $lines)) : '- No events or meetings recorded yet.';
    }
}
