<?php

namespace App\Services\Moderation;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * martin-ha/toxic-comment-model through the Hugging Face Inference API: the probability that a text is toxic.
 * Results are cached per text for a day. Null when no token is set or the service cannot be reached, so the
 * word filter keeps working on its own.
 */
class ToxicityClassifier
{
    public function score(string $text): ?float
    {
        $token = config('services.huggingface.token');
        $text = trim($text);
        if (! $token || $text === '') {
            return null;
        }

        return Cache::remember('toxicity:'.sha1($text), now()->addDay(), function () use ($token, $text): ?float {
            try {
                $response = Http::withToken($token)->acceptJson()->timeout(4)->retry(1, 300, throw: false)
                    ->post((string) config('services.huggingface.toxicity_url'), ['inputs' => mb_substr($text, 0, 1000)]);
            } catch (ConnectionException $e) {
                Log::warning('Toxicity check unavailable', ['error' => $e->getMessage()]);

                return null;
            }
            if (! $response->successful()) {
                Log::warning('Toxicity check failed', ['status' => $response->status()]);

                return null;
            }
            // [[{"label":"toxic","score":0.97},{"label":"non-toxic","score":0.03}]] (sometimes not nested)
            $labels = collect($response->json())->flatten(1)->filter(fn ($row) => is_array($row) && isset($row['label'], $row['score']));
            $toxic = $labels->first(fn (array $row) => strtolower((string) $row['label']) === 'toxic');

            return $toxic ? (float) $toxic['score'] : null;
        });
    }

    public function isToxic(string $text): bool
    {
        $score = $this->score($text);

        return $score !== null && $score >= (float) config('services.huggingface.toxicity_threshold', 0.8);
    }
}
