<?php

namespace App\Services\Ai;

use Anthropic\Core\Exceptions\APIException;
use App\Services\Membership\ReceiptReading;
use Illuminate\Support\Facades\Log;

/** Reads amount, date, reference and payee from a bank-transfer receipt (image or PDF) with Claude. */
class ReceiptReader
{
    private const PROMPT = <<<'TXT'
    This is a payment receipt or bank-transfer confirmation submitted as proof of a club membership fee.
    Read it and report: the amount paid in Malaysian ringgit, the transaction date (YYYY-MM-DD), the
    transaction reference or ID, and the recipient/payee name exactly as printed. Use null for anything
    you cannot read clearly. Set confidence between 0 and 1 for how sure you are of all four values together;
    use a low value for screenshots that are blurry, cropped, edited-looking, or not a payment receipt.
    TXT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'amount_rm' => ['type' => ['number', 'null']],
            'date' => ['type' => ['string', 'null']],
            'reference' => ['type' => ['string', 'null']],
            'payee' => ['type' => ['string', 'null']],
            'confidence' => ['type' => 'number'],
        ],
        'required' => ['amount_rm', 'date', 'reference', 'payee', 'confidence'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly AiClient $ai) {}

    /** Null when AI is not configured, the call fails, or the model declines; the payment then goes to review. */
    public function read(string $path, string $mime): ?ReceiptReading
    {
        $client = $this->ai->client();
        if (! $client || ! is_file($path)) {
            return null;
        }

        $source = ['type' => 'base64', 'mediaType' => $mime, 'data' => base64_encode((string) file_get_contents($path))];
        $file = $mime === 'application/pdf' ? ['type' => 'document', 'source' => $source] : ['type' => 'image', 'source' => $source];

        try {
            $message = $client->beta->messages->create(
                maxTokens: 2000,
                messages: [['role' => 'user', 'content' => [$file, ['type' => 'text', 'text' => self::PROMPT]]]],
                model: $this->ai->model(),
                // Server-side retry on Anthropic's recommended model if the request is declined.
                fallbacks: 'default',
                outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::SCHEMA]],
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (APIException $e) {
            Log::warning('Receipt reading failed', ['error' => $e->getMessage()]);

            return null;
        }

        if ($message->stopReason === 'refusal') {
            return null;
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (! is_array($data)) {
                    return null;
                }

                return new ReceiptReading(
                    isset($data['amount_rm']) ? (int) round((float) $data['amount_rm'] * 100) : null,
                    $data['date'] ?? null,
                    $data['reference'] ?? null,
                    $data['payee'] ?? null,
                    (float) ($data['confidence'] ?? 0),
                );
            }
        }

        return null;
    }
}
