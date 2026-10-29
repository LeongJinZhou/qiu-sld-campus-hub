<?php

namespace App\Services\Membership;

use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Models\Club;
use App\Models\ClubFee;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use App\Services\Finance\Ledger;
use App\Services\Time\SessionResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Joining, paying and activation. No refund, no exit: there is no leave action. */
class MembershipService
{
    public const LENGTH_DAYS = 365;

    public function __construct(
        private readonly SessionResolver $sessions,
        private readonly Ledger $ledger,
        private readonly AuditLogger $audit,
    ) {}

    /** The SLD-approved fee for the current session, or null when none is approved yet. */
    public function fee(Club $club): ?ClubFee
    {
        return ClubFee::where('club_id', $club->id)->where('session_id', $this->sessions->current()->id)->whereNotNull('approved_at')->first();
    }

    /**
     * Starts joining (or renewing during grace). RM0 activates at once; a paid fee needs the
     * no-refund / no-exit acknowledgement and leaves the membership pending until payment is confirmed.
     */
    public function join(Club $club, User $user, bool $acknowledged): Membership
    {
        if (! $user->student?->isActive()) {
            throw new DomainException('Only active QIU students can join clubs.');
        }
        $fee = $this->fee($club) ?? throw new DomainException('Joining opens once SLD approves this club\'s fee for the session.');

        $membership = Membership::firstOrNew(['club_id' => $club->id, 'user_id' => $user->id]);
        if ($membership->exists && in_array($membership->status, [MembershipStatus::Active, MembershipStatus::Alumni], true)) {
            throw new DomainException('You are already a member.');
        }
        if ($fee->amount_sen > 0 && ! $acknowledged) {
            throw new DomainException('Please confirm that membership fees are non-refundable and members cannot leave.');
        }

        // A member in grace keeps their status while renewing; everyone else waits for payment.
        if (! $membership->exists || $membership->status !== MembershipStatus::Grace) {
            $membership->status = MembershipStatus::Pending;
        }
        $membership->acknowledged_at = now();
        $membership->save();

        if ($fee->amount_sen === 0) {
            $payment = $membership->payments()->create([
                'club_id' => $club->id, 'user_id' => $user->id, 'method' => 'free', 'amount_sen' => 0, 'status' => PaymentStatus::Confirmed, 'verified_at' => now(),
            ]);
            $this->activate($payment);
        }
        $this->audit->log('membership.join', $club, ['user_id' => $user->id, 'fee_sen' => $fee->amount_sen]);

        return $membership->refresh();
    }

    /** 365 days from confirmation; renewing starts a new 365 days. Records ledger income for paid fees. */
    public function activate(MembershipPayment $payment, ?string $ledgerDescription = null): void
    {
        DB::transaction(function () use ($payment, $ledgerDescription): void {
            $membership = $payment->membership;
            $membership->update([
                'status' => MembershipStatus::Active,
                'starts_on' => today(),
                'expires_on' => today()->addDays(self::LENGTH_DAYS),
                'grace_ends_on' => null,
                'last_reminded_at' => null,
            ]);
            if ($payment->amount_sen > 0) {
                $this->ledger->income($membership->club, today(), $ledgerDescription ?? "Membership fee (online) – {$payment->user->name}", $payment->amount_sen, $payment);
            }
            $membership->club->forceFill(['last_activity_at' => now()])->save();
        });

        $payment->user->notify(new DocumentNotification(
            "Welcome to {$payment->membership->club->name}",
            'Your membership is active until '.$payment->membership->fresh()->expires_on->toFormattedDateString().'.',
            route('clubs.show', $payment->membership->club),
        ));
    }

    /** Treasurer decision on a payment in the review queue. */
    public function review(MembershipPayment $payment, User $treasurer, bool $approve, ?string $note): void
    {
        // Conditional update: only one reviewer can move it out of the queue, even with stale copies.
        $moved = MembershipPayment::whereKey($payment->id)->where('status', PaymentStatus::PendingReview)->update([
            'status' => $approve ? PaymentStatus::Confirmed : PaymentStatus::Rejected,
            'review_note' => $note,
            'verified_by' => $treasurer->id,
            'verified_at' => now(),
        ]);
        if ($moved === 0) {
            throw new DomainException('This payment is not waiting for review.');
        }
        $payment->refresh();
        $this->audit->log($approve ? 'payment.confirmed' : 'payment.rejected', $payment->membership->club, ['payment_id' => $payment->id, 'note' => $note]);

        if ($approve) {
            $this->activate($payment);
        } else {
            $payment->user->notify(new DocumentNotification('Membership payment not accepted', $note ?: 'Please check your receipt and try again.', route('membership.join', $payment->membership->club)));
        }
    }
}
