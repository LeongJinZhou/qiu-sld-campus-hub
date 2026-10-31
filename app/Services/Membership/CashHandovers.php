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
use App\Services\Ai\CashPhotoChecker;
use App\Services\AuditLogger;
use App\Services\Time\SessionResolver;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Zxing\QrReader;

/**
 * Cash payment handover: student picks a committee member → Accept/Decline → both tap ready → the student shows
 * their own profile QR → the member's camera captures it automatically with the cash in view → the server reads
 * the QR in the photo (must be this student's) and checks the cash (AI, or the member confirms) → both tap done →
 * membership active, ledger income filed under Membership as cash received by that member, receipts to both.
 * Requests expire after 7 days.
 */
class CashHandovers
{
    public function __construct(
        private readonly MembershipService $memberships,
        private readonly SessionResolver $sessions,
        private readonly AuditLogger $audit,
        private readonly CashPhotoChecker $cashChecker,
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

    /** Each side taps "ready"; once both have, the student shows their profile QR to the member's camera. */
    public function ready(CashHandover $handover, User $user): void
    {
        $side = $this->assertParty($handover, $user);
        $this->assertStatus($handover, [HandoverStatus::Accepted, HandoverStatus::StudentReady, HandoverStatus::CommitteeReady]);
        $handover->forceFill([$side === 'student' ? 'student_ready_at' : 'committee_ready_at' => now()]);
        $both = $handover->student_ready_at && $handover->committee_ready_at;
        $handover->status = $both ? HandoverStatus::BothReady : ($side === 'student' ? HandoverStatus::StudentReady : HandoverStatus::CommitteeReady);
        if ($both) {
            $handover->qr_token_hash = hash('sha256', (string) $handover->student->qr_token);
        }
        $handover->save();
    }

    /** What the student's screen shows: their own profile QR (the same one on their profile page). */
    public function qrPayload(CashHandover $handover, User $student): ?string
    {
        $this->assertParty($handover, $student, 'student');

        return in_array($handover->status, [HandoverStatus::BothReady, HandoverStatus::PhotoTaken], true) ? route('profile.public', $student->qr_token) : null;
    }

    /**
     * The member's photo must contain this student's profile QR (read on the server) and the cash. AI checks the
     * cash when available; otherwise the member confirms it on the next screen. Retakes allowed.
     */
    public function photo(CashHandover $handover, User $committee, UploadedFile $photo): void
    {
        $this->assertParty($handover, $committee, 'committee');
        $this->assertStatus($handover, [HandoverStatus::BothReady, HandoverStatus::PhotoTaken]);

        $decoded = $this->decode($photo->getRealPath());
        $token = $decoded !== null && preg_match('#/p/([A-Za-z0-9_-]+)#', $decoded, $m) ? $m[1] : null;
        if ($token === null) {
            throw new DomainException("The photo doesn't show the student's QR code clearly. Retake it with the student's phone and the cash in view.");
        }
        if (! hash_equals((string) $handover->qr_token_hash, hash('sha256', $token))) {
            throw new DomainException("That QR code belongs to someone else. Scan {$handover->student->name}'s own QR code.");
        }

        $cash = $this->cashChecker->check($photo->getRealPath(), $handover->payment->amount_sen);
        if ($cash !== null && ! $cash['cash_visible']) {
            throw new DomainException('No cash can be seen in the photo. Retake it with the cash next to the student\'s phone.');
        }

        if ($handover->photo_path) {
            Storage::disk('local')->delete($handover->photo_path);
        }
        $handover->update([
            'photo_path' => $photo->store("cash-handovers/{$handover->id}", 'local'),
            'cash_check' => $cash !== null ? 'ai' : 'needs_confirmation',
            'status' => HandoverStatus::PhotoTaken,
            'photo_taken_at' => now(),
            'student_done_at' => null,
            'committee_done_at' => null,
        ]);
    }

    /** Without AI, the member confirms the cash in the photo matches the fee. */
    public function confirmCash(CashHandover $handover, User $committee): void
    {
        $this->assertParty($handover, $committee, 'committee');
        $this->assertStatus($handover, [HandoverStatus::PhotoTaken]);
        $handover->update(['cash_check' => 'committee']);
    }

    /** Both tap "Transaction done" → membership active, ledger income, receipts to both. */
    public function done(CashHandover $handover, User $user): void
    {
        $side = $this->assertParty($handover, $user);
        $this->assertStatus($handover, [HandoverStatus::PhotoTaken]);
        if (! in_array($handover->cash_check, ['ai', 'committee'], true)) {
            throw new DomainException('The committee member must confirm the cash in the photo first.');
        }
        $handover->update([$side === 'student' ? 'student_done_at' : 'committee_done_at' => now()]);

        if ($handover->student_done_at && $handover->committee_done_at) {
            $completed = DB::transaction(function () use ($handover): bool {
                // Only the first of the two final taps completes it.
                if (CashHandover::whereKey($handover->id)->where('status', HandoverStatus::PhotoTaken)->update(['status' => HandoverStatus::Completed, 'completed_at' => now()]) === 0) {
                    return false;
                }
                $payment = $handover->payment;
                $payment->update(['status' => PaymentStatus::Confirmed, 'verified_by' => $handover->committee_user_id, 'verified_at' => now()]);
                $this->memberships->activate($payment, "Membership fee (cash) – {$handover->student->name}");
                $this->audit->log('payment.cash_completed', $payment->club, ['payment_id' => $payment->id, 'collected_by' => $handover->committee_user_id]);

                return true;
            });
            if (! $completed) {
                return;
            }
            $receipt = new DocumentNotification(
                'Cash payment receipt',
                sprintf('RM %s received by %s from %s for %s on %s. Ref: CASH-%d.', number_format($handover->payment->amount_sen / 100, 2), $handover->committee->name, $handover->student->name, $handover->payment->club->name, now()->format('D, j M Y, g:i A'), $handover->payment_id),
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
