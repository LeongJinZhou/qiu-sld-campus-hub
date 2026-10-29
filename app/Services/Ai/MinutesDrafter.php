<?php

namespace App\Services\Ai;

use Anthropic\Core\Exceptions\APIException;
use Illuminate\Support\Facades\Log;

/** Turns the Secretary's rough notes into minutes fields (purpose, summary points, action items). */
class MinutesDrafter
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'purpose' => ['type' => 'string'],
            'summary' => ['type' => 'array', 'items' => ['type' => 'string']],
            'actions' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => ['task' => ['type' => 'string'], 'pic' => ['type' => 'string'], 'deadline' => ['type' => 'string']],
                'required' => ['task', 'pic', 'deadline'],
                'additionalProperties' => false,
            ]],
        ],
        'required' => ['purpose', 'summary', 'actions'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly AiClient $ai) {}

    /** @return array{purpose: string, summary: list<string>, actions: list<array{task: string, pic: string, deadline: string}>}|null */
    public function draft(string $club, string $meetingTitle, string $notes): ?array
    {
        $client = $this->ai->client();
        if (! $client || trim($notes) === '') {
            return null;
        }

        $prompt = <<<TXT
        Draft meeting minutes for a university student club ({$club}), meeting "{$meetingTitle}", from the
        secretary's notes below. Write in plain, formal English as short statements in past tense.
        - purpose: one sentence.
        - summary: one point per activity or matter discussed, in the order they happened.
        - actions: only real follow-up tasks with the person in charge as named in the notes; use "N/A"
          for a missing deadline. Return an empty list when there are none. Do not invent names or facts.

        Notes:
        {$notes}
        TXT;

        try {
            $message = $client->beta->messages->create(
                maxTokens: 4000,
                messages: [['role' => 'user', 'content' => $prompt]],
                model: $this->ai->model(),
                fallbacks: 'default',
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::SCHEMA]],
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (APIException $e) {
            Log::warning('Minutes drafting failed', ['error' => $e->getMessage()]);

            return null;
        }
        if ($message->stopReason === 'refusal') {
            return null;
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);

                return is_array($data) ? $data : null;
            }
        }

        return null;
    }
}
