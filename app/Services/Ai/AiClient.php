<?php

namespace App\Services\Ai;

use Anthropic\Client;

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
}
