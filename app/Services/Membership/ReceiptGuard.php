<?php

namespace App\Services\Membership;

use App\Enums\PaymentStatus;
use App\Models\MembershipPayment;
use App\Services\Finance\OrderIds;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * One receipt pays once, across every club: the same screenshot file (SHA-256) or the same bank transaction
 * reference cannot back a second payment. A student may re-send their own receipt after a rejection;
 * nobody else may use it.
 */
class ReceiptGuard
{
    public function __construct(private readonly OrderIds $ids) {}

    public function fingerprint(UploadedFile $file): string
    {
        return (string) hash_file('sha256', (string) $file->getRealPath());
    }

    /** Throws when the screenshot is already behind another payment. */
    public function assertUnusedFile(string $hash, int $userId): void
    {
        if ($this->usedBy(MembershipPayment::where('receipt_hash', $hash), $userId)) {
            throw new DomainException('This receipt has already been used for another payment. Upload the receipt of your own transfer.');
        }
    }

    public function key(?string $reference): ?string
    {
        return $this->ids->reference($reference) ?: null;
    }

    public function referenceTaken(string $key, MembershipPayment $payment): bool
    {
        return $this->usedBy(MembershipPayment::where('reference_key', $key)->whereKeyNot($payment->id), $payment->user_id);
    }

    /**
     * Runs $claim while no other worker can claim the same reference.
     *
     * @template T
     *
     * @param  callable(): T  $claim
     * @return T
     */
    public function exclusively(string $key, callable $claim): mixed
    {
        return Cache::lock("receipt-reference:{$key}", 15)->block(10, $claim);
    }

    /** @param Builder<MembershipPayment> $query */
    private function usedBy($query, int $userId): bool
    {
        return $query->where(fn ($q) => $q->where('status', '!=', PaymentStatus::Rejected)->orWhere('user_id', '!=', $userId))->exists();
    }
}
