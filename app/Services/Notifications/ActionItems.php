<?php

namespace App\Services\Notifications;

use App\Enums\ApprovalDecision;
use App\Enums\DocumentStatus;
use App\Enums\HandoverStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\Approval;
use App\Models\CashHandover;
use App\Models\Event;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Time\SessionResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Things a person has started or must do, shown at the top of Notifications until they are done:
 * payments in progress, cash handovers, receipts to review, indemnities to sign, approvals waiting.
 */
class ActionItems
{
    public function __construct(private readonly ApprovalEngine $engine, private readonly SessionResolver $sessions) {}

    /** @return list<array{key: string, kind: string, title: string, body: string, url: string, since: ?string}> */
    public function for(User $user): array
    {
        return [...$this->payments($user), ...$this->handovers($user), ...$this->receiptsToReview($user), ...$this->indemnities($user), ...$this->approvals($user)];
    }

    /** Short-lived cache for the badge count shown on every page. */
    public function count(User $user): int
    {
        return Cache::remember("actions:{$user->id}", 30, fn () => count($this->for($user)));
    }

    public static function forget(User|int $user): void
    {
        Cache::forget('actions:'.($user instanceof User ? $user->id : $user));
    }

    /** Joined a paid club but not paid yet (and no cash handover running). */
    private function payments(User $user): array
    {
        return Membership::with('club')->where('user_id', $user->id)->where('status', MembershipStatus::Pending)->get()
            ->reject(fn (Membership $m) => MembershipPayment::where('membership_id', $m->id)->whereIn('status', [PaymentStatus::Reading, PaymentStatus::PendingReview, PaymentStatus::CashPending])->exists())
            ->map(fn (Membership $m) => [
                'key' => "payment:{$m->id}", 'kind' => 'payment', 'title' => "Finish paying for {$m->club->name}",
                'body' => 'You started joining but have not paid yet. Pay online or in cash to activate your membership.',
                'url' => route('membership.join', $m->club), 'since' => $m->updated_at?->toIso8601String(),
            ])->values()->all();
    }

    /** Cash handovers still open, with what is waiting on this person. */
    private function handovers(User $user): array
    {
        return CashHandover::with('student', 'committee', 'payment.club')
            ->where(fn ($q) => $q->where('student_id', $user->id)->orWhere('committee_user_id', $user->id))
            ->whereNotIn('status', [HandoverStatus::Completed, HandoverStatus::Declined, HandoverStatus::Expired])->get()
            ->map(function (CashHandover $h) use ($user) {
                $isStudent = $h->student_id === $user->id;
                $other = $isStudent ? $h->committee->name : $h->student->name;
                $body = match (true) {
                    $h->status === HandoverStatus::Requested && ! $isStudent => "{$other} wants to pay you in cash. Accept or decline.",
                    $h->status === HandoverStatus::Requested => "Waiting for {$other} to accept.",
                    $h->status === HandoverStatus::PhotoTaken => 'The photo is taken. Confirm the transaction to finish.',
                    default => "Meet {$other} and continue where you left off.",
                };

                return [
                    'key' => "handover:{$h->id}", 'kind' => 'handover', 'title' => "Cash payment in progress · {$h->payment->club->name}",
                    'body' => $body, 'url' => route('handovers.show', $h), 'since' => $h->updated_at?->toIso8601String(),
                ];
            })->values()->all();
    }

    /** Committee members: online receipts the AI could not confirm. */
    private function receiptsToReview(User $user): array
    {
        $clubIds = RoleAssignment::where('user_id', $user->id)->where('scope_type', ScopeType::Club)->where('role', ScopedRole::ClubCommittee)
            ->where('session_id', $this->sessions->current()->id)->pluck('scope_id');
        if ($clubIds->isEmpty()) {
            return [];
        }

        return MembershipPayment::with('club')->whereIn('club_id', $clubIds)->where('status', PaymentStatus::PendingReview)->get()
            ->groupBy('club_id')->map(fn ($payments) => [
                'key' => "review:{$payments->first()->club_id}", 'kind' => 'review',
                'title' => $payments->count().' '.($payments->count() === 1 ? 'receipt' : 'receipts').' to check · '.$payments->first()->club->name,
                'body' => 'Online payments the system could not confirm automatically.', 'url' => route('payments.review', $payments->first()->club),
                'since' => $payments->min('created_at')?->toIso8601String(),
            ])->values()->all();
    }

    /** Registered for an outdoor event but not signed the indemnity, while signing is still open. */
    private function indemnities(User $user): array
    {
        return Event::where('is_outdoor', true)->where('status', DocumentStatus::Approved)->where('starts_at', '>', now()->addDays(3))
            ->whereHas('registrations', fn ($q) => $q->where('user_id', $user->id))
            ->whereDoesntHave('indemnities', fn ($q) => $q->where('user_id', $user->id))->get()
            ->map(fn (Event $e) => [
                'key' => "indemnity:{$e->id}", 'kind' => 'indemnity', 'title' => "Sign the indemnity for {$e->name}",
                'body' => 'Required for outdoor events. Signing closes '.$e->indemnityClosesAt()->format('D, j M Y').'; without it you cannot clock in.',
                'url' => route('events.indemnity', $e), 'since' => null,
            ])->values()->all();
    }

    /** Approval steps waiting for this person (advisor, President, staff approvers, SLD). */
    private function approvals(User $user): array
    {
        $waiting = Approval::with('document')->where('decision', ApprovalDecision::Pending)
            ->whereHas('document', fn ($q) => $q->where('status', DocumentStatus::InReview))->get()
            ->filter(fn (Approval $a) => $this->engine->canAct($a, $user));

        return $waiting->isEmpty() ? [] : [[
            'key' => 'approvals', 'kind' => 'approval', 'title' => $waiting->count().' '.($waiting->count() === 1 ? 'document waits' : 'documents wait').' for your approval',
            'body' => 'Approve, return with comments, or reject.', 'url' => route('approvals.index'), 'since' => $waiting->min('updated_at')?->toIso8601String(),
        ]];
    }
}
