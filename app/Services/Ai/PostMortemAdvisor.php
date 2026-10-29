<?php

namespace App\Services\Ai;

use Anthropic\Core\Exceptions\APIException;
use App\Services\Events\PostMortemForm;
use Illuminate\Support\Facades\Log;

/** Suggests improvements and recommendations from what the committee wrote went well and what was hard. */
class PostMortemAdvisor
{
    public function __construct(private readonly AiClient $ai) {}

    /**
     * @param  array<string, array{well: string, challenges: string, improvement: string}>  $rows
     * @return array{improvements: array<string, string>, recommendations: list<string>}|null
     */
    public function suggest(string $event, array $rows): ?array
    {
        $client = $this->ai->client();
        if (! $client) {
            return null;
        }
        $areas = '';
        foreach ($rows as $key => $row) {
            $areas .= "{$key}. ".(PostMortemForm::AREAS[$key] ?? $key)."\n  Went well: {$row['well']}\n  Challenges: {$row['challenges']}\n";
        }
        $schema = [
            'type' => 'object',
            'properties' => [
                'improvements' => ['type' => 'object', 'properties' => array_map(fn () => ['type' => 'string'], PostMortemForm::AREAS), 'required' => array_keys(PostMortemForm::AREAS), 'additionalProperties' => false],
                'recommendations' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['improvements', 'recommendations'],
            'additionalProperties' => false,
        ];
        $prompt = <<<TXT
        A university student club ran the event "{$event}" and is writing its post mortem. For each area, suggest one
        concrete, practical improvement based only on the challenges listed (write "N/A" where nothing went wrong
        or the area was not filled in). Then give 3 short recommendations for future committees. Plain English,
        one sentence each.

        {$areas}
        TXT;

        try {
            $message = $client->beta->messages->create(
                maxTokens: 4000,
                messages: [['role' => 'user', 'content' => $prompt]],
                model: $this->ai->model(),
                fallbacks: 'default',
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (APIException $e) {
            Log::warning('Post mortem suggestions failed', ['error' => $e->getMessage()]);

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
