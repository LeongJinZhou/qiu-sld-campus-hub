<?php

namespace App\Services\Ai;

/**
 * Looks at a cash-handover photo: is there cash in it, next to a phone showing a QR code? A camera can find a
 * QR code reliably on its own, but not banknotes, so this check is AI's; without AI the committee member confirms.
 */
class CashPhotoChecker
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'cash_visible' => ['type' => 'boolean'],
            'qr_visible' => ['type' => 'boolean'],
            'estimated_total_rm' => ['type' => ['number', 'null']],
            'note' => ['type' => 'string'],
        ],
        'required' => ['cash_visible', 'qr_visible', 'estimated_total_rm', 'note'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly AiClient $ai) {}

    /** @return array{cash_visible: bool, qr_visible: bool, estimated_total_rm: ?float, note: string}|null null when AI is off or fails */
    public function check(string $path, int $expectedSen): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        $image = ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => mime_content_type($path) ?: 'image/jpeg', 'data' => base64_encode((string) file_get_contents($path))]];
        $prompt = sprintf(
            'This photo was taken by a university club committee member collecting a membership fee of RM %.2f in cash. '
            .'Is Malaysian cash (banknotes or coins) clearly visible, and is a phone screen showing a QR code visible? '
            .'If you can read the banknotes, estimate the total in RM, otherwise null. Keep the note to one short sentence.',
            $expectedSen / 100,
        );
        $data = $this->ai->json([$image, ['type' => 'text', 'text' => $prompt]], self::SCHEMA, 400);

        return $data === null ? null : [
            'cash_visible' => (bool) $data['cash_visible'], 'qr_visible' => (bool) $data['qr_visible'],
            'estimated_total_rm' => isset($data['estimated_total_rm']) ? (float) $data['estimated_total_rm'] : null, 'note' => (string) $data['note'],
        ];
    }
}
