<?php

namespace App\Services\Dashboard;

use App\Enums\ApprovalDecision;
use App\Enums\DocumentStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Models\Agm;
use App\Models\Approval;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventDocumentItem;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Services\Agm\AgmPeriods;
use App\Services\Finance\Ledger;
use App\Services\Time\SessionResolver;

/** SLD at a glance (BUILD_PROMPT Phase 5.1): club status, approvals, overdue work, events, AGM, money. */
class SldDashboard
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly AgmPeriods $periods,
        private readonly SessionResolver $sessions,
    ) {}

    /** @return array<string, mixed> */
    public function build(): array
    {
        $session = $this->sessions->current();
        $period = $this->periods->current();
        $clubs = Club::orderBy('name')->get();
        $waiting = Approval::with('document')->where('decision', ApprovalDecision::Pending)
            ->whereHas('document', fn ($q) => $q->where('status', DocumentStatus::InReview))->get()
            // Only the step each document is actually waiting on.
            ->groupBy('document_id')->map(fn ($steps) => $steps->sortBy('step_no')->first())->values();

        return [
            'session' => $session->name,
            'clubs_by_status' => collect(['active', 'at_risk', 'inactive'])->mapWithKeys(fn ($s) => [$s => $clubs->where('status', $s)->count()]),
            'approvals' => $waiting->groupBy(fn (Approval $a) => $a->role->label())->map(fn ($g, $role) => [
                'role' => $role, 'waiting' => $g->count(), 'overdue' => $g->filter(fn (Approval $a) => $a->due_at?->isPast())->count(),
            ])->sortByDesc('waiting')->values(),
            'overdue' => [
                'approvals' => $waiting->filter(fn (Approval $a) => $a->due_at?->isPast())->count(),
                'post_event' => EventDocumentItem::whereNull('submitted_at')->whereDate('due_on', '<', today())->count(),
            ],
            'events_this_month' => Event::with('club')->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()])->orderBy('starts_at')->get()
                ->map(fn (Event $e) => ['slug' => $e->slug, 'name' => $e->name, 'club' => $e->club->name, 'date' => $e->starts_at->format('D j M'), 'status' => $e->status->value]),
            'agm' => $period ? [
                'session' => $period->session->name,
                'clubs' => $clubs->count(),
                'scheduled' => Agm::where('agm_period_id', $period->id)->count(),
                'reports' => Agm::where('agm_period_id', $period->id)->whereHas('report', fn ($q) => $q->whereIn('status', [DocumentStatus::InReview, DocumentStatus::Approved]))->count(),
                'packages' => Agm::where('agm_period_id', $period->id)->whereNotNull('package_submitted_at')->count(),
            ] : null,
            'members' => collect(MembershipStatus::cases())->mapWithKeys(fn ($s) => [$s->value => Membership::where('status', $s)->count()]),
            'payments' => [
                'paid_sen' => (int) MembershipPayment::whereIn('status', [PaymentStatus::Confirmed, PaymentStatus::AutoConfirmed])->whereDate('created_at', '>=', $session->starts_on)->sum('amount_sen'),
                'pending_review' => MembershipPayment::where('status', PaymentStatus::PendingReview)->count(),
            ],
            'balances' => $clubs->map(fn (Club $c) => ['slug' => $c->slug, 'name' => $c->name, 'ready' => $this->ledger->isReady($c), 'balance_sen' => $this->ledger->balance($c)])
                ->sortByDesc('balance_sen')->values(),
        ];
    }
}
