<?php

use App\Enums\PaymentStatus;
use App\Services\Membership\ReceiptReading;
use App\Services\Membership\ReceiptVerifier;
use Carbon\CarbonImmutable;

$today = CarbonImmutable::parse('2026-11-01');
$good = fn (array $o = []) => new ReceiptReading(...array_replace(['amountSen' => 3000, 'date' => '2026-10-30', 'reference' => 'MBB123456', 'payee' => 'GPS CLUB QIU', 'confidence' => 0.95], $o));
$assess = fn (?ReceiptReading $r, array $used = []) => (new ReceiptVerifier)->assess($r, 3000, 'GPS Club QIU', $used, $today);

it('auto-confirms when amount, date, reference and payee all match', function () use ($assess, $good) {
    expect($assess($good()))->toBe(['status' => PaymentStatus::AutoConfirmed, 'flags' => []]);
});

it('rejects a reused reference number, ignoring spacing and case', function () use ($assess, $good) {
    expect($assess($good(), ['mbb 123-456'])['status'])->toBe(PaymentStatus::Rejected);
});

it('sends anything unclear to the Treasurer review queue with a reason', function (array $override, string $flag) use ($assess, $good) {
    $result = $assess($good($override));
    expect($result['status'])->toBe(PaymentStatus::PendingReview)->and($result['flags'])->toContain($flag);
})->with([
    [['amountSen' => 2500], 'amount_mismatch'],
    [['payee' => 'Ali Bin Abu'], 'payee_mismatch'],
    [['date' => '2026-10-20'], 'old_receipt'],
    [['date' => '2026-11-05'], 'future_date'],
    [['reference' => null], 'no_reference'],
    [['confidence' => 0.5], 'low_confidence'],
]);

it('reviews receipts the AI could not read', function () use ($assess) {
    expect($assess(null))->toBe(['status' => PaymentStatus::PendingReview, 'flags' => ['unreadable']]);
});
