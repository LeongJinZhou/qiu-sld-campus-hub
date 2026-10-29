<?php

namespace App\Services\Membership;

use App\Enums\HandoverStatus;
use App\Enums\PaymentStatus;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\CashHandover;
use App\Models\Membership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use App\Services\Time\SessionResolver;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Zxing\QrReader;

/**
 * Cash payment handover: student picks a committee member → Accept/Decline → both tap ready →
 * student shows a one-time QR → member photographs the QR with the cash → server decodes the QR in the photo
 * → both tap done → membership active, ledger income credited to that member, receipts to both. 7-day expiry.
 */
class CashHandovers
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly SessionResolver $sessions,
        private readonly AuditLogger $audit,
    ) {}

    public function request(Membership $membership, User $committee): CashHandover
    {
        $fee = $this->memberships->fee($membership->club) ?? throw new DomainException('This club has no approved fee.');
        if (! $this->isCommittee($committee, $membership)) {
            throw new DomainException('Choose a committee member of this club.');
        }
        if ($membership->payments()->whereHas('handover', fn ($q) => $q->whereNotIn('status', [HandoverStatus::Declined, HandoverStatus::Completed, HandoverStatus::Expired]))->exists()) {
            throw new DomainException('You already have a cash handover in progress.');
        }

        return DB::transaction(function () use ($membership, $committee, $fee): CashHandover {
            $payment = $membership->payments()->create([
                'club_id' => $membership->club_id, 'user_id' => $membership->user_id, 'method' => 'cash',
                'amount_sen' => $fee->amount_sen, 'status' => PaymentStatus::CashPending, 'collected_by' => $committee->id,
            ]);
            $handover = CashHandover::create([
                'payment_id' => $payment->id, 'student_id' => $membership->user_id, 'committee_user_id' => $committee->id,
                'status' => HandoverStatus::Requested, 'expires_at' => now()->addDays(CashHandover::LIFETIME_DAYS),
            ]);
            $committee->notify(new DocumentNotification('Cash membership payment request', "{$membership->user->name} wants to pay {$membership->club->name}'s fee in cash.", route('handovers.show', $handover)));

            return $handover;
        });
    }

    public function respond(CashHandover $handover, User $committee, bool $accept): void
    {
        $this->assertParty($handover, $committee, 'committee');
        $this->assertStatus($handover, [HandoverStatus::Requested]);
        $handover->update($accept
            ? ['status' => HandoverStatus::Accepted, 'accepted_at' => now()]
            : ['status' => HandoverStatus::Declined, 'declined_at' => now()]);
        if (! $accept) {
            $handover->payment->update(['status' => PaymentStatus::Rejected, 'review_note' => 'Declined by committee member']);
        }
    }

    /** Each side taps "ready"; once both have, the student's screen shows the one-time QR. */
    public function ready(CashHandover $handover, User $user): void
    {
        $side = $this->assertParty($handover, $user);
        $this->assertStatus($handover, [HandoverStatus::Accepted, HandoverStatus::StudentReady, HandoverStatus::CommitteeReady]);
        $handover->forceFill([$side === 'student' ? 'student_ready_at' : 'committee_ready_at' => now()]);
        $both = $handover->student_ready_at && $handover->committee_ready_at;
        $handover->status = $both ? HandoverStatus::BothReady : ($side === 'student' ? HandoverStatus::StudentReady : HandoverStatus::CommitteeReady);
        if ($both) {
            $handover->qr_token_hash = hash('sha256', $this->token($handover));
        }
        $handover->save();
    }

    /** The one-time token shown as a QR on the student's screen; only once both sides are ready. */
    public function qrPayload(CashHandover $handover, User $student): ?string
    {
        $this->assertParty($handover, $student, 'student');

        return in_array($handover->status, [HandoverStatus::BothReady, HandoverStatus::PhotoTaken], true) ? $this->token($handover) : null;
    }

    /** The committee member's photo must contain this handover's QR (decoded server-side). Retakes allowed. */
    public function photo(CashHandover $handover, User $committee, UploadedFile $photo): void
    {
        $this->assertParty($handover, $committee, 'committee');
        $this->assertStatus($handover, [HandoverStatus::BothReady, HandoverStatus::PhotoTaken]);

        $decoded = $this->decode($photo->getRealPath());
        if ($decoded === null || ! hash_equals((string) $handover->qr_token_hash, hash('sha256', $decoded))) {
            throw new DomainException("The photo doesn't show this payment's QR code clearly. Retake it with the student's screen and the cash in view.");
        }

        if ($handover->photo_path) {
            Storage::disk('local')->delete($handover->photo_path);
        }
        $handover->update([
            'photo_path' => $photo->store("cash-handovers/{$handover->id}", 'local'),
            'status' => HandoverStatus::PhotoTaken,
            'photo_taken_at' => now(),
            'student_done_at' => null,
            'committee_done_at' => null,
        ]);
    }

    /** Both tap "Transaction done" → membership active, ledger income, receipts to both. */
    public function done(CashHandover $handover, User $user): void
    {
        $side = $this->assertParty($handover, $user);
        $this->assertStatus($handover, [HandoverStatus::PhotoTaken]);
        $handover->update([$side === 'student' ? 'student_done_at' : 'committee_done_at' => now()]);

        if ($handover->student_done_at && $handover->committee_done_at) {
            $completed = DB::transaction(function () use ($handover): bool {
                // Only the first of the two final taps completes it.
                if (CashHandover::whereKey($handover->id)->where('status', HandoverStatus::PhotoTaken)->update(['status' => HandoverStatus::Completed, 'completed_at' => now()]) === 0) {
                    return false;
                }
                $payment = $handover->payment;
                $payment->update(['status' => PaymentStatus::Confirmed, 'verified_by' => $handover->committee_user_id, 'verified_at' => now()]);
                $this->memberships->activate($payment, "Membership fee (cash) – collected by {$handover->committee->name}");
                $this->audit->log('payment.cash_completed', $payment->club, ['payment_id' => $payment->id, 'collected_by' => $handover->committee_user_id]);

                return true;
            });
            if (! $completed) {
                return;
            }
            $receipt = new DocumentNotification(
                'Cash payment receipt',
                sprintf('RM %s received by %s from %s for %s on %s. Ref: CASH-%d.', number_format($handover->payment->amount_sen / 100, 2), $handover->committee->name, $handover->student->name, $handover->payment->club->name, now()->toDayDateTimeString(), $handover->payment_id),
                route('handovers.show', $handover),
            );
            $handover->student->notify($receipt);
            $handover->committee->notify($receipt);
        }
    }

    /** Requests not completed within 7 days expire. */
    public function expireStale(): int
    {
        $stale = CashHandover::whereNotIn('status', [HandoverStatus::Completed, HandoverStatus::Declined, HandoverStatus::Expired])->where('expires_at', '<', now())->get();
        foreach ($stale as $handover) {
            $handover->update(['status' => HandoverStatus::Expired]);
            $handover->payment->update(['status' => PaymentStatus::Rejected, 'review_note' => 'Cash handover expired']);
        }

        return $stale->count();
    }

    /** @return 'student'|'committee' */
    public function sideOf(CashHandover $handover, User $user): ?string
    {
        return match ($user->id) {
            $handover->student_id => 'student',
            $handover->committee_user_id => 'committee',
            default => null,
        };
    }

    private function isCommittee(User $user, Membership $membership): bool
    {
        return RoleAssignment::where('scope_type', ScopeType::Club)->where('scope_id', $membership->club_id)
            ->where('session_id', $this->sessions->current()->id)->where('role', ScopedRole::ClubCommittee)
            ->where('user_id', $user->id)->exists();
    }

    /** Derived, never stored: changes if either "ready" is re-tapped, and dies with the handover. */
    private function token(CashHandover $handover): string
    {
        return 'QIU-CASH:'.substr(hash_hmac('sha256', "{$handover->id}|{$handover->student_ready_at?->timestamp}|{$handover->committee_ready_at?->timestamp}", (string) config('app.key')), 0, 32);
    }

    private function decode(string $path): ?string
    {
        try {
            // TRY_HARDER: without it ~1 in 10 clean phone photos of the QR fail to decode.
            $text = (new QrReader($path))->text(['TRY_HARDER' => true]);

            return is_string($text) && $text !== '' ? $text : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return 'student'|'committee' */
    private function assertParty(CashHandover $handover, User $user, ?string $required = null): string
    {
        $side = $this->sideOf($handover, $user);
        if ($side === null || ($required && $side !== $required)) {
            throw new DomainException('This step is not yours to take.');
        }
        if ($handover->expires_at->isPast()) {
            throw new DomainException('This handover request has expired.');
        }

        return $side;
    }

    /** @param list<HandoverStatus> $allowed */
    private function assertStatus(CashHandover $handover, array $allowed): void
    {
        if (! in_array($handover->status, $allowed, true)) {
            throw new DomainException('This step has already been done or is not available yet.');
        }
    }
}
