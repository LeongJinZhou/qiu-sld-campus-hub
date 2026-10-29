<?php

namespace App\Services\Ai;

use App\Models\Event;
use Illuminate\Support\Collection;

/** Writes the "Details" paragraph of each event slide in the Annual Activities Report. */
class AnnualReportWriter
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => ['events' => ['type' => 'array', 'items' => [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer'], 'details' => ['type' => 'string']],
            'required' => ['id', 'details'],
            'additionalProperties' => false,
        ]]],
        'required' => ['events'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly AiClient $ai) {}

    /**
     * @param  Collection<int, Event>  $events
     * @return array<int, string>|null event id => details
     */
    public function write(string $club, Collection $events): ?array
    {
        if ($events->isEmpty()) {
            return [];
        }
        $records = $events->map(fn (Event $e) => "- id {$e->id}: {$e->name}, {$e->starts_at->format('j M Y')}, at {$e->venue}. ".mb_substr((string) $e->summary, 0, 800))->join("\n");
        $prompt = <<<TXT
        Write the "Details" paragraph for each event in a university student club's ({$club}) Annual Activities
        Report. Each paragraph is 2–4 sentences in plain, formal English, past tense, third person ("The club
        organised…"), describing what the event was and its purpose. Use only the facts given; do not invent
        names, numbers or outcomes. Return one entry per event id.

        Events:
        {$records}
        TXT;

        $data = $this->ai->json($prompt, self::SCHEMA);

        return $data ? collect($data['events'])->pluck('details', 'id')->all() : null;
    }
}
