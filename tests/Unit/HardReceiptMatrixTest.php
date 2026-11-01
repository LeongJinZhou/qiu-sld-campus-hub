<?php

use App\Enums\PaymentStatus;
use App\Services\Membership\ReceiptReading;
use App\Services\Membership\ReceiptVerifier;
use Carbon\CarbonImmutable;

dataset('hard receipt combinations', function () {
    foreach (['exact' => 3000, 'underpaid' => 2999, 'overpaid' => 3001, 'missing' => null] as $amountName => $amount) {
        foreach (['low' => 0.79, 'threshold' => 0.8, 'high' => 0.99] as $confidenceName => $confidence) {
            foreach (['today' => '2026-11-02', 'seven days' => '2026-10-26', 'eight days' => '2026-10-25', 'future' => '2026-11-03'] as $dateName => $date) {
                foreach (['fresh', 'duplicate'] as $reference) {
                    yield "$amountName / $confidenceName / $dateName / $reference" => [$amount, $confidence, $date, $reference];
                }
            }
        }
    }
});

it('requires all payment checks and rejects reused transaction references', function (?int $amount, float $confidence, string $date, string $reference) {
    $reading = new ReceiptReading($amount, $date, 'TXN-AUDIT-1', 'Fixture Club QIU', $confidence, 'Audit Student');
    $expected = $reference === 'duplicate' ? PaymentStatus::Rejected
        : ($amount === 3000 && $confidence >= 0.8 && in_array($date, ['2026-11-02', '2026-10-26'], true)
            ? PaymentStatus::AutoConfirmed : PaymentStatus::PendingReview);
    $decision = (new ReceiptVerifier)->assess($reading, 3000, 'Fixture Club QIU',
        $reference === 'duplicate' ? ['txn audit 1'] : [], CarbonImmutable::parse('2026-11-02'), 'Audit Student');

    expect($decision['status'])->toBe($expected);
    if ($reference === 'duplicate') {
        expect($decision['flags'])->toContain('duplicate_reference');
    }
})->with('hard receipt combinations')->group('hard-audit');
