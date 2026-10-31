<?php

namespace App\Services\Membership;

use App\Enums\PaymentStatus;
use App\Jobs\ReadReceipt;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Services\Ai\ReceiptReader;
use App\Services\AuditLogger;
use App\Services\Finance\OrderIds;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Online payment: student pays via the club's link, uploads the receipt, AI reads it, auto-confirm or review. */
class OnlinePayments
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly ReceiptReader $reader,
        private readonly ReceiptVerifier $verifier,
        private readonly AuditLogger $audit,
        private readonly ReceiptGuard $guard,
        private readonly OrderIds $ids,
    ) {}

    public function submit(Membership $membership, UploadedFile $receipt): MembershipPayment
    {
        $fee = $this->memberships->fee($membership->club) ?? throw new DomainException('This club has no approved fee.');
        if ($fee->amount_sen === 0) {
            throw new DomainException('This club is free to join.');
        }
        if ($membership->payments()->whereIn('status', [PaymentStatus::Reading, PaymentStatus::PendingReview])->exists()) {
            throw new DomainException('Your previous receipt is still being checked.');
        }
        $hash = $this->guard->fingerprint($receipt);
        $this->guard->assertUnusedFile($hash, $membership->user_id);

        $payment = $membership->payments()->create([
            'club_id' => $membership->club_id,
            'user_id' => $membership->user_id,
            'method' => 'online',
            'amount_sen' => $fee->amount_sen,
            'receipt_path' => $receipt->store("payment-receipts/{$membership->club_id}", 'local'),
            'receipt_hash' => $hash,
            'status' => PaymentStatus::Reading,
        ]);
        ReadReceipt::dispatch($payment->id);

        return $payment;
    }

    /** Runs in the queue: read with AI, then auto-confirm, send to review, or reject a reused reference. */
    public function process(MembershipPayment $payment): void
    {
        // Claim the payment so a retried or duplicate job cannot process it twice.
        if (MembershipPayment::whereKey($payment->id)->where('status', PaymentStatus::Reading)->update(['status' => PaymentStatus::PendingReview]) === 0) {
            return;
        }
        $path = Storage::disk('local')->path((string) $payment->receipt_path);
        $reading = $this->reader->read($path, (string) (mime_content_type($path) ?: 'image/jpeg'));
        $payee = $payment->event_id ? $payment->event?->payment_account_name : $this->memberships->fee($payment->club)?->account_name;

        $key = $this->guard->key($reading?->reference);
        $decide = function () use ($payment, $reading, $payee, $key): array {
            $decision = $this->verifier->assess($reading, $payment->amount_sen, $payee, $key && $this->guard->referenceTaken($key, $payment) ? [$key] : [],
                CarbonImmutable::today(), $payment->user->name);
            $rejected = $decision['status'] === PaymentStatus::Rejected;
            $payment->update([
                'ai_amount_sen' => $reading?->amountSen,
                'ai_date' => $reading?->date && strtotime($reading->date) ? $reading->date : null,
                'ai_reference' => $reading?->reference,
                'ai_payee' => $reading?->payee,
                'ai_payer' => $reading?->payer,
                'ai_confidence' => $reading?->confidence,
                'reference' => $reading?->reference,
                'reference_key' => $key,
                'order_id' => $key && ! $rejected ? $this->orderIdWith($payment, $key) : $payment->order_id,
                'flags' => $decision['flags'],
                'status' => $decision['status'],
                'review_note' => $rejected ? 'This transaction ID has already been used for another payment.' : null,
                'verified_at' => $decision['status'] === PaymentStatus::AutoConfirmed ? now() : null,
            ]);

            return $decision;
        };
        // Claim the reference under a lock so two students sending the same receipt at once cannot both pass.
        $decision = $key ? $this->guard->exclusively($key, $decide) : $decide();
        $this->audit->log('payment.'.$decision['status']->value, $payment->club, ['payment_id' => $payment->id, 'flags' => $decision['flags']]);

        if ($decision['status'] === PaymentStatus::AutoConfirmed) {
            $this->memberships->activate($payment);
        }
        if ($decision['status'] === PaymentStatus::Rejected) {
            $this->memberships->notifyRejected($payment->fresh());
        }
    }

    /** The order ID ends with the bank reference, unless that exact ID already exists (a re-sent receipt). */
    private function orderIdWith(MembershipPayment $payment, string $key): string
    {
        $id = $this->ids->withReference((string) $payment->order_id, $key);

        return MembershipPayment::where('order_id', $id)->whereKeyNot($payment->id)->exists() ? (string) $payment->order_id : $id;
    }
}
