<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalDecision;
use App\Enums\DocumentStatus;
use App\Http\Requests\DecideApprovalRequest;
use App\Models\Approval;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocumentPresenter;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    /** Approver inbox: steps currently waiting for this user, soonest due first. */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $waiting = Approval::query()
            ->with('document.club', 'document.event')
            ->where('decision', ApprovalDecision::Pending)
            ->whereHas('document', fn ($q) => $q->where('status', DocumentStatus::InReview))
            ->orderBy('due_at')
            ->get()
            ->filter(fn (Approval $a) => $this->engine->canAct($a, $user))
            ->values();

        return Inertia::render('Approvals/Inbox', [
            'items' => $waiting->map(fn (Approval $a) => [
                'id' => $a->id,
                'type' => $a->document->type->label(),
                'club' => $a->document->club->name,
                'event' => $a->document->event?->name,
                'role' => $a->role->label(),
                'step_no' => $a->step_no,
                'due_at' => $a->due_at?->toFormattedDateString(),
                'overdue' => $a->due_at?->isPast() ?? false,
            ]),
        ]);
    }

    public function show(Request $request, Approval $approval, DocumentPresenter $presenter): Response
    {
        abort_unless($this->engine->canAct($approval, $request->user()) || $request->user()->can('view', $approval->document), 403);

        return Inertia::render('Approvals/Review', [
            'approvalId' => $approval->id,
            'role' => $approval->role->label(),
            'canAct' => $this->engine->canAct($approval, $request->user()),
            'document' => $presenter->present($approval->document),
        ]);
    }

    public function decide(DecideApprovalRequest $request, Approval $approval): RedirectResponse
    {
        $user = $request->user();
        $comment = (string) $request->validated('comment');

        try {
            match ($request->validated('decision')) {
                'approve' => $this->engine->approve($approval, $user, (string) $request->validated('signature'), $comment ?: null),
                'return' => $this->engine->returnToClub($approval, $user, $comment),
                'reject' => $this->engine->reject($approval, $user, $comment),
            };
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('approvals.index')->with('success', 'Decision recorded.');
    }
}
