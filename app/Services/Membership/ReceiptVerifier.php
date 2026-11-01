<?php

namespace App\Services\Membership;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/**
 * Decides whether an online payment can be confirmed automatically: amount, date, reference
 * and payee must all match; reused references are rejected; receipts older than 7 days are flagged; a sender
 * name that is not the student's goes to review (someone else's screenshot, or a parent paying).
 */
class ReceiptVerifier
{
    public const MIN_CONFIDENCE = 0.8;

    public const MAX_AGE_DAYS = 7;

    /**
     * @param  list<string>  $usedReferences  references already on accepted payments
     * @return array{status: PaymentStatus, flags: list<string>}
     */
    public function assess(?ReceiptReading $reading, int $feeSen, ?string $accountName, array $usedReferences, CarbonImmutable $today, ?string $studentName = null): array
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

        // Who sent the money must be readable and be the student: missing evidence is never auto-confirmed.
        if ($studentName !== null) {
            if ($reading->payer === null || trim($reading->payer) === '') {
                $flags[] = 'no_payer';
            } elseif (! $this->namesMatch($reading->payer, $studentName)) {
                $flags[] = 'payer_mismatch';
            }
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

    /** "LEONG JIN ZHOU" matches "Jinzhou Leong": most words of the name appear in the printed name, in any order or spacing. */
    public function namesMatch(?string $printed, string $name): bool
    {
        $haystack = $this->normalise($printed);
        $words = array_values(array_filter(array_map(fn ($w) => $this->normalise($w), preg_split('/\s+/', $this->stripTitles($name)) ?: []), fn ($w) => strlen($w) >= 2));
        if ($haystack === '' || $words === []) {
            return false;
        }
        $found = count(array_filter($words, fn ($w) => str_contains($haystack, $w)));

        return $found >= max(1, (int) ceil(count($words) / 2));
    }

    private function stripTitles(string $name): string
    {
        return (string) preg_replace('/\b(bin|binti|bt|b|a\/l|a\/p|al|ap)\b/i', ' ', $name);
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
