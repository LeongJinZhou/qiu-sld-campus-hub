<?php

namespace App\Services\Membership;

use App\Enums\PaymentStatus;
use App\Jobs\ReadReceipt;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Services\Ai\ReceiptReader;
use App\Services\AuditLogger;
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

        $payment = $membership->payments()->create([
            'club_id' => $membership->club_id,
            'user_id' => $membership->user_id,
            'method' => 'online',
            'amount_sen' => $fee->amount_sen,
            'receipt_path' => $receipt->store("payment-receipts/{$membership->club_id}", 'local'),
            'status' => PaymentStatus::Reading,
        ]);
        ReadReceipt::dispatch($payment->id);

        return $payment;
    }

    /** Runs in the queue: read with AI, then auto-confirm, send to review, or reject a reused reference. */
    public function process(MembershipPayment $payment): void
    {
        if ($payment->status !== PaymentStatus::Reading) {
            return;
        }
        $path = Storage::disk('local')->path((string) $payment->receipt_path);
        $reading = $this->reader->read($path, (string) (mime_content_type($path) ?: 'image/jpeg'));
        $fee = $this->memberships->fee($payment->club);

        $used = MembershipPayment::where('club_id', $payment->club_id)->whereKeyNot($payment->id)
            ->whereIn('status', [PaymentStatus::AutoConfirmed, PaymentStatus::Confirmed, PaymentStatus::PendingReview])
            ->whereNotNull('reference')->pluck('reference')->all();
        $decision = $this->verifier->assess($reading, $payment->amount_sen, $fee?->account_name, $used, CarbonImmutable::today());

        $payment->update([
            'ai_amount_sen' => $reading?->amountSen,
            'ai_date' => $reading?->date && strtotime($reading->date) ? $reading->date : null,
            'ai_reference' => $reading?->reference,
            'ai_payee' => $reading?->payee,
            'ai_confidence' => $reading?->confidence,
            'reference' => $reading?->reference,
            'flags' => $decision['flags'],
            'status' => $decision['status'],
            'verified_at' => $decision['status'] === PaymentStatus::AutoConfirmed ? now() : null,
        ]);
        $this->audit->log('payment.'.$decision['status']->value, $payment->club, ['payment_id' => $payment->id, 'flags' => $decision['flags']]);

        if ($decision['status'] === PaymentStatus::AutoConfirmed) {
            $this->memberships->activate($payment);
        }
    }
}
