<?php

namespace App\Services\Membership;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/**
 * Decides whether an online payment can be confirmed automatically: amount, date, reference
 * and payee must all match; reused references are rejected; receipts older than 7 days are flagged.
 */
class ReceiptVerifier
{
    public const MIN_CONFIDENCE = 0.8;

    public const MAX_AGE_DAYS = 7;

    /**
     * @param  list<string>  $usedReferences  references already on accepted payments
     * @return array{status: PaymentStatus, flags: list<string>}
     */
    public function assess(?ReceiptReading $reading, int $feeSen, ?string $accountName, array $usedReferences, CarbonImmutable $today): array
    {
        if ($reading === null) {
            return ['status' => PaymentStatus::PendingReview, 'flags' => ['unreadable']];
        }

        $flags = [];
        $reference = $this->normalise($reading->reference);
        if ($reference !== '' && in_array($reference, array_map(fn ($r) => $this->normalise($r), $usedReferences), true)) {
            return ['status' => PaymentStatus::Rejected, 'flags' => ['duplicate_reference']];
        }
        if ($reference === '') {
            $flags[] = 'no_reference';
        }
        if ($reading->amountSen !== $feeSen) {
            $flags[] = 'amount_mismatch';
        }
        if (! $accountName || ! str_contains($this->normalise($reading->payee), $this->normalise($accountName))) {
            $flags[] = 'payee_mismatch';
        }

        $date = $this->date($reading->date);
        if ($date === null) {
            $flags[] = 'no_date';
        } elseif ($date->gt($today)) {
            $flags[] = 'future_date';
        } elseif ($date->diffInDays($today) > self::MAX_AGE_DAYS) {
            $flags[] = 'old_receipt';
        }
        if ($reading->confidence < self::MIN_CONFIDENCE) {
            $flags[] = 'low_confidence';
        }

        return ['status' => $flags === [] ? PaymentStatus::AutoConfirmed : PaymentStatus::PendingReview, 'flags' => $flags];
    }

    private function normalise(?string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $value));
    }

    private function date(?string $value): ?CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse($value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
