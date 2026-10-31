<?php

namespace App\Services\Events;

use App\Enums\PaymentStatus;
use App\Jobs\ReadReceipt;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use App\Services\Finance\Ledger;
use App\Services\Uploads\ImageUpload;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Paid events: the committee sets the fee, payment link, account name and up to two payment QR codes; students
 * pay online (receipt checked like membership) or in cash (the same QR handover). Each confirmed payment marks
 * the registration paid and goes into the club ledger under that event, with its order ID.
 */
class EventPayments
{
    public const MAX_QR = 2;

    public function __construct(private readonly Ledger $ledger, private readonly AuditLogger $audit, private readonly ImageUpload $images) {}

    /**
     * @param  array{fee_rm: float|int|string, payment_url: ?string, payment_account_name: ?string}  $data
     * @param  list<UploadedFile>  $qrUploads
     * @param  list<string>  $removeQr  stored paths to remove
     */
    public function configure(Event $event, User $by, array $data, array $qrUploads = [], array $removeQr = []): void
    {
        $paths = array_values(array_diff($event->payment_qr_paths ?? [], $removeQr));
        foreach ($removeQr as $path) {
            Storage::disk('public')->delete($path);
        }
        if (count($paths) + count($qrUploads) > self::MAX_QR) {
            throw new DomainException('Upload at most '.self::MAX_QR.' payment QR codes.');
        }
        foreach ($qrUploads as $upload) {
            $paths[] = $this->images->store($upload, "payment-qr/events/{$event->id}");
        }
        $fee = (int) round(((float) $data['fee_rm']) * 100);
        $event->update(['fee_sen' => $fee, 'payment_url' => $data['payment_url'] ?: null, 'payment_account_name' => $data['payment_account_name'] ?: null, 'payment_qr_paths' => $paths]);
        $this->audit->log('event.payment_settings', $event, ['fee_sen' => $fee, 'qr_codes' => count($paths)]);
    }

    public function submitOnline(EventRegistration $registration, UploadedFile $receipt): MembershipPayment
    {
        $event = $registration->event;
        $this->assertPayable($registration);
        if ($registration->payments()->whereIn('status', [PaymentStatus::Reading, PaymentStatus::PendingReview, PaymentStatus::CashPending])->exists()) {
            throw new DomainException('Your previous payment is still being checked.');
        }
        $payment = MembershipPayment::create([
            'club_id' => $event->club_id, 'user_id' => $registration->user_id, 'event_id' => $event->id, 'event_registration_id' => $registration->id,
            'method' => 'online', 'amount_sen' => $event->fee_sen, 'status' => PaymentStatus::Reading,
            'receipt_path' => $receipt->store("payment-receipts/{$event->club_id}", 'local'),
        ]);
        ReadReceipt::dispatch($payment->id);

        return $payment;
    }

    /** A pending cash payment for the event; the handover itself is started by CashHandovers. */
    public function cashPayment(EventRegistration $registration, User $committee): MembershipPayment
    {
        $this->assertPayable($registration);
        $event = $registration->event;

        return MembershipPayment::create([
            'club_id' => $event->club_id, 'user_id' => $registration->user_id, 'event_id' => $event->id, 'event_registration_id' => $registration->id,
            'method' => 'cash', 'amount_sen' => $event->fee_sen, 'status' => PaymentStatus::CashPending, 'collected_by' => $committee->id,
        ]);
    }

    /** Confirmed payment: registration paid, money filed under the event in the club ledger, student told. */
    public function confirm(MembershipPayment $payment, ?string $description = null): void
    {
        $registration = $payment->registration()->with('event.club', 'user')->firstOrFail();
        DB::transaction(function () use ($payment, $registration, $description): void {
            $registration->update(['status' => 'registered', 'paid_at' => now()]);
            $this->ledger->income($registration->event->club, today(), $description ?? "{$registration->event->name} fee ({$payment->method}) – {$registration->user->name}",
                $payment->amount_sen, $payment, category: 'event', eventId: $registration->event_id, method: $payment->method,
                handledBy: $payment->method === 'cash' ? $payment->collector : null);
            $registration->event->club->forceFill(['last_activity_at' => now()])->save();
        });
        $registration->user->notify(new DocumentNotification(
            "You're registered for {$registration->event->name}",
            "Payment received (ref {$payment->order_id}).".($registration->event->is_outdoor ? ' Remember to sign the indemnity form.' : ''),
            route($registration->event->is_outdoor ? 'events.indemnity' : 'events.show', $registration->event),
        ));
    }

    private function assertPayable(EventRegistration $registration): void
    {
        if ($registration->isPaidUp()) {
            throw new DomainException('You have already paid for this event.');
        }
        if ($registration->event->fee_sen <= 0) {
            throw new DomainException('This event is free.');
        }
    }
}
