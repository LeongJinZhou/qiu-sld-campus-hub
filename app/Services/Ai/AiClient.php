<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Illuminate\Support\Facades\Log;

/** The Claude client, or null when no API key is configured (AI features then degrade to manual review). */
class AiClient
{
    public function client(): ?Client
    {
        $key = config('services.anthropic.key');

        return $key ? new Client(apiKey: $key) : null;
    }

    public function model(): string
    {
        return (string) config('services.anthropic.model');
    }

    /**
     * One structured-output call: the reply is JSON matching $schema, or null when AI is off or the call fails.
     *
     * @param  string|list<array<string, mixed>>  $prompt  text, or content blocks (e.g. an image then the question)
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null
     */
    public function json(string|array $prompt, array $schema, int $maxTokens = 4000): ?array
    {
        $client = $this->client();
        if (! $client) {
            return null;
        }
        try {
            $message = $client->beta->messages->create(
                maxTokens: $maxTokens,
                messages: [['role' => 'user', 'content' => $prompt]],
                model: $this->model(),
                fallbacks: 'default',
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (APIException $e) {
            Log::warning('AI call failed', ['error' => $e->getMessage()]);

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
