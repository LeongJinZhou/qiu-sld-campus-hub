<?php

namespace App\Services\Finance;

use App\Enums\PaymentStatus;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\LedgerEntry;
use App\Models\MembershipDrive;
use App\Models\MembershipPayment;
use Illuminate\Support\Collection;

/**
 * Who paid what, per event and per membership session: a tally the Treasurer can match against the ledger
 * (payments counted vs. money filed), split by method, by the committee member who received cash and, for
 * membership, by membership drive.
 */
class PaymentBreakdown
{
    private const OPEN = [PaymentStatus::Reading, PaymentStatus::PendingReview, PaymentStatus::CashPending, PaymentStatus::AwaitingReceipt];

    /** @return array<string, mixed> */
    public function event(Event $event): array
    {
        $registrations = EventRegistration::with(['user.student', 'payments.collector'])->where('event_id', $event->id)->orderBy('created_at')->orderBy('id')->get();
        $paid = $this->paidPayments(MembershipPayment::where('event_id', $event->id));
        $ledger = LedgerEntry::where('event_id', $event->id)->get();
        $fromPayments = $ledger->where('source_type', $this->paymentMorph());

        $rows = $registrations->map(function (EventRegistration $r) use ($event): array {
            $payment = $r->payments->sortByDesc('id')->first();

            return [
                'name' => $r->user->name, 'student_number' => $r->user->student?->student_number, 'email' => $r->user->email,
                'status' => $this->registrationStatus($r, $payment, $event),
                'date' => ($r->paid_at ?? $r->created_at)->toDateString(),
                'method' => $payment?->method, 'order_id' => $payment?->order_id,
                'amount_sen' => $payment?->status->isPaid() ? $payment->amount_sen : 0,
                'collected_by' => $payment?->method === 'cash' ? $payment->collector?->name : null,
            ];
        })->values();

        return [
            'title' => $event->name,
            'subtitle' => $event->starts_at->format('D, j M Y').' · fee '.($event->fee_sen > 0 ? 'RM '.number_format($event->fee_sen / 100, 2) : 'free'),
            'tiles' => [
                ['label' => 'Registered', 'count' => $registrations->count()],
                ['label' => 'Paid', 'count' => $rows->where('status', 'Paid')->count()],
                ['label' => 'Not paid yet', 'count' => $rows->whereIn('status', ['Payment pending', 'Being checked'])->count()],
                ['label' => 'Fees collected', 'sen' => $paid->sum('amount_sen')],
                ['label' => 'Other income', 'sen' => $ledger->sum('income_sen') - $fromPayments->sum('income_sen')],
                ['label' => 'Expenses', 'sen' => $ledger->sum('expense_sen')],
                ['label' => 'Net for this event', 'sen' => $ledger->sum('income_sen') - $ledger->sum('expense_sen'), 'net' => true],
            ],
            'check' => $this->check($paid->sum('amount_sen'), $fromPayments->sum('income_sen'), $event->fee_sen * $rows->where('status', 'Paid')->count()),
            'groups' => [
                ['title' => 'By method', 'items' => $this->byMethod($paid)],
                ['title' => 'Cash received by', 'items' => $this->byCollector($paid)],
            ],
            'rows' => $rows->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function membership(Club $club, AcademicSession $session): array
    {
        $payments = MembershipPayment::with(['user.student', 'collector'])->where('club_id', $club->id)->whereNotNull('membership_id')->where('method', '!=', 'free')
            ->whereBetween('created_at', [$session->starts_on->startOfDay(), $session->ends_on->endOfDay()])->orderBy('created_at')->orderBy('id')->get();
        $paid = $payments->filter(fn (MembershipPayment $p) => $p->status->isPaid());
        $free = MembershipPayment::where('club_id', $club->id)->whereNotNull('membership_id')->where('method', 'free')
            ->whereBetween('created_at', [$session->starts_on->startOfDay(), $session->ends_on->endOfDay()])->count();
        $ledgerSen = LedgerEntry::where('club_id', $club->id)->where('category', 'membership')->where('source_type', $this->paymentMorph())
            ->whereIn('source_id', $paid->pluck('id'))->sum('income_sen');
        $drives = MembershipDrive::where(fn ($q) => $q->whereNull('club_id')->orWhere('club_id', $club->id))
            ->where('starts_at', '<=', $session->ends_on->endOfDay())->where('ends_at', '>=', $session->starts_on->startOfDay())->orderBy('starts_at')->get();
        $driveOf = fn (MembershipPayment $p): ?MembershipDrive => $drives->first(fn (MembershipDrive $d) => ($p->verified_at ?? $p->created_at)->between($d->starts_at, $d->ends_at));

        return [
            'title' => 'Membership fees',
            'subtitle' => "Session {$session->name}",
            'tiles' => [
                ['label' => 'Joined or renewed (paid)', 'count' => $paid->count()],
                ['label' => 'Joined free', 'count' => $free],
                ['label' => 'Not confirmed yet', 'count' => $payments->filter(fn (MembershipPayment $p) => in_array($p->status, self::OPEN, true))->count()],
                ['label' => 'Fees collected', 'sen' => $paid->sum('amount_sen')],
            ],
            'check' => $this->check($paid->sum('amount_sen'), (int) $ledgerSen, null),
            'groups' => [
                ['title' => 'By membership drive', 'items' => [
                    ...$drives->map(fn (MembershipDrive $d) => $this->tally("{$d->title} ({$d->starts_at->format('j M')} – {$d->ends_at->format('j M')})", $paid->filter(fn ($p) => $driveOf($p)?->is($d))))->all(),
                    $this->tally('Outside membership drives', $paid->filter(fn ($p) => $driveOf($p) === null)),
                ]],
                ['title' => 'By method', 'items' => $this->byMethod($paid)],
                ['title' => 'Cash received by', 'items' => $this->byCollector($paid)],
            ],
            'rows' => $payments->map(fn (MembershipPayment $p) => [
                'name' => $p->user->name, 'student_number' => $p->user->student?->student_number, 'email' => $p->user->email,
                'status' => $p->status->isPaid() ? 'Paid' : ($p->status === PaymentStatus::Rejected ? 'Rejected' : 'Being checked'),
                'date' => ($p->verified_at ?? $p->created_at)->toDateString(), 'method' => $p->method, 'order_id' => $p->order_id,
                'amount_sen' => $p->status->isPaid() ? $p->amount_sen : 0,
                'collected_by' => $p->method === 'cash' ? $p->collector?->name : null,
                'drive' => $p->status->isPaid() ? $driveOf($p)?->title : null,
            ])->values()->all(),
        ];
    }

    private function paymentMorph(): string
    {
        return (new MembershipPayment)->getMorphClass();
    }

    /** @return Collection<int, MembershipPayment> */
    private function paidPayments($query): Collection
    {
        return $query->with('collector')->whereIn('status', [PaymentStatus::Confirmed, PaymentStatus::AutoConfirmed])->get();
    }

    private function registrationStatus(EventRegistration $r, ?MembershipPayment $payment, Event $event): string
    {
        return match (true) {
            $r->status !== 'pending_payment' => $payment?->status->isPaid() ? 'Paid' : ($event->fee_sen > 0 ? 'Registered (no fee)' : 'Registered'),
            $payment !== null && in_array($payment->status, self::OPEN, true) => 'Being checked',
            default => 'Payment pending',
        };
    }

    /** Payments counted vs. money filed in the ledger (and, for events, paid places × fee). */
    private function check(int $payments, int $ledger, ?int $expected): array
    {
        return ['payments_sen' => $payments, 'ledger_sen' => $ledger, 'expected_sen' => $expected,
            'ok' => $payments === $ledger && ($expected === null || $expected === $payments)];
    }

    /** @param Collection<int, MembershipPayment> $paid */
    private function byMethod(Collection $paid): array
    {
        return $paid->groupBy('method')->map(fn (Collection $g, string $method) => $this->tally(ucfirst($method), $g))->sortBy('label')->values()->all();
    }

    /** @param Collection<int, MembershipPayment> $paid */
    private function byCollector(Collection $paid): array
    {
        return $paid->where('method', 'cash')->groupBy(fn (MembershipPayment $p) => $p->collector?->name ?? 'Unknown')
            ->map(fn (Collection $g, string $name) => $this->tally($name, $g))->sortBy('label')->values()->all();
    }

    /** @param Collection<int, MembershipPayment> $payments */
    private function tally(string $label, Collection $payments): array
    {
        return ['label' => $label, 'count' => $payments->count(), 'sen' => $payments->sum('amount_sen')];
    }
}
