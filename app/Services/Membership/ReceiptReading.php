<?php

namespace App\Services\Membership;

/** What the AI read off a payment receipt. Money in sen. */
final readonly class ReceiptReading
{
    public function __construct(
        public ?int $amountSen,
        public ?string $date,
        public ?string $reference,
        public ?string $payee,
        public float $confidence,
    ) {}
}
