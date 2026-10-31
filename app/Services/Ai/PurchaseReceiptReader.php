<?php

namespace App\Services\Ai;

/** Reads a shop receipt: every item with quantity and price, the total, the shop and the date. */
class PurchaseReceiptReader
{
    private const PROMPT = <<<'TXT'
    This is a photo of a shop receipt for things a university club bought for an event, in Malaysia.
    List every item line: its name as printed (expand obvious abbreviations), the quantity (1 if not shown),
    the unit price and the line total in Malaysian ringgit. Also give the receipt's grand total, the shop name
    and the date (YYYY-MM-DD). Use null for anything you cannot read. Set confidence between 0 and 1 for how
    sure you are of the items and total together; use a low value if the photo is blurry, cut off, or not a receipt.
    TXT;

    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'items' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'quantity' => ['type' => 'number'],
                    'unit_price_rm' => ['type' => ['number', 'null']],
                    'total_rm' => ['type' => 'number'],
                ],
                'required' => ['name', 'quantity', 'unit_price_rm', 'total_rm'],
                'additionalProperties' => false,
            ]],
            'total_rm' => ['type' => ['number', 'null']],
            'shop' => ['type' => ['string', 'null']],
            'date' => ['type' => ['string', 'null']],
            'confidence' => ['type' => 'number'],
        ],
        'required' => ['items', 'total_rm', 'shop', 'date', 'confidence'],
        'additionalProperties' => false,
    ];

    public function __construct(private readonly AiClient $ai) {}

    /**
     * @return array{items: list<array{name: string, quantity: int, unit_sen: int, total_sen: int}>, total_sen: ?int, shop: ?string, date: ?string, confidence: float}|null
     */
    public function read(string $path, string $mime): ?array
    {
        if (! is_file($path)) {
            return null;
        }
        $source = ['type' => 'base64', 'mediaType' => $mime, 'data' => base64_encode((string) file_get_contents($path))];
        $file = $mime === 'application/pdf' ? ['type' => 'document', 'source' => $source] : ['type' => 'image', 'source' => $source];
        $data = $this->ai->json([$file, ['type' => 'text', 'text' => self::PROMPT]], self::SCHEMA, 3000);
        if ($data === null) {
            return null;
        }
        $sen = fn ($rm): int => (int) round((float) $rm * 100);

        return [
            'items' => array_values(array_map(function (array $i) use ($sen): array {
                $quantity = max(1, (int) round((float) $i['quantity']));
                $total = $sen($i['total_rm']);

                return ['name' => mb_substr(trim((string) $i['name']), 0, 120), 'quantity' => $quantity, 'unit_sen' => $i['unit_price_rm'] !== null ? $sen($i['unit_price_rm']) : intdiv($total, $quantity), 'total_sen' => $total];
            }, array_slice($data['items'] ?? [], 0, 60))),
            'total_sen' => isset($data['total_rm']) ? $sen($data['total_rm']) : null,
            'shop' => isset($data['shop']) ? mb_substr((string) $data['shop'], 0, 150) : null,
            'date' => isset($data['date']) && strtotime((string) $data['date']) ? (string) $data['date'] : null,
            'confidence' => (float) ($data['confidence'] ?? 0),
        ];
    }
}
