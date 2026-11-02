<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalDecision;
use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Http\Requests\DecideApprovalRequest;
use App\Models\Approval;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Approvals\PaperSignoff;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\DocumentPresenter;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalEngine $engine, private readonly PaperSignoff $paper) {}

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

        // Approver dashboard: what I have signed, and how quickly.
        $mine = Approval::with('document.club')->where('approver_user_id', $user->id)
            ->whereIn('decision', [ApprovalDecision::Approved, ApprovalDecision::Returned, ApprovalDecision::Rejected])
            ->where('step_no', '>', 1)->latest('decided_at')->get();
        $thisMonth = $mine->filter(fn (Approval $a) => $a->decided_at?->isCurrentMonth());
        $turnaround = $thisMonth->map(fn (Approval $a) => $a->created_at->diffInHours($a->decided_at) / 24)->avg();

        return Inertia::render('Approvals/Inbox', [
            'stats' => [
                'waiting' => $waiting->count(),
                'overdue' => $waiting->filter(fn (Approval $a) => $a->due_at?->isPast())->count(),
                'decided_this_month' => $thisMonth->count(),
                'avg_days' => $turnaround !== null ? round($turnaround, 1) : null,
            ],
            'recent' => $mine->take(8)->map(fn (Approval $a) => [
                'document_id' => $a->document_id, 'type' => $a->document->type->label(), 'club' => $a->document->club->name,
                'decision' => $a->decision->value, 'decided_at' => $a->decided_at?->format('j M Y'),
            ])->values(),
            'items' => $waiting->map(fn (Approval $a) => [
                'id' => $a->id,
                'type' => $a->document->type->label(),
                'club' => $a->document->club->name,
                'event' => $a->document->event?->name,
                'role' => $a->role->label(),
                'step_no' => $a->step_no,
                'due_at' => $a->due_at?->format('j M Y'),
                'overdue' => $a->due_at?->isPast() ?? false,
                'progress' => app(DocumentPresenter::class)->progress($a->document),
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
            'downloads' => app(DocumentExporter::class)->formats($approval->document),
            'document' => $presenter->present($approval->document),
            'exceptions' => $this->engine->exceptions($approval->document),
            'decidesExceptions' => $approval->role === ChainRole::SldOfficer,
            'paper' => $this->paper->present($approval),
            'paperOptions' => $approval->role === ChainRole::SldOfficer && $this->engine->canAct($approval, $request->user()) ? $this->paper->options() : [],
        ]);
    }

    public function decide(DecideApprovalRequest $request, Approval $approval): RedirectResponse
    {
        $user = $request->user();
        $comment = (string) $request->validated('comment');

        try {
            match ($request->validated('decision')) {
                'approve' => $this->engine->approve($approval, $user, (string) $request->validated('signature'), $comment ?: null, $request->validated('signature_strokes'), $request->boolean('accept_exceptions')),
                'return' => $this->engine->returnToClub($approval, $user, $comment, $request->validated('sections') ?? []),
                'reject' => $this->engine->reject($approval, $user, $comment, $request->validated('sections') ?? []),
            };
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        // The final approval: a moment worth celebrating.
        if ($request->validated('decision') === 'approve' && $approval->document->fresh()->status === DocumentStatus::Approved) {
            return to_route('approvals.index')->with('success', 'Decision recorded.')->with('celebrate', "{$approval->document->type->label()} approved");
        }

        return to_route('approvals.index')->with('success', 'Decision recorded.');
    }

    /** SLD sends the document to higher authorities to sign on paper. */
    public function paperStart(Request $request, Approval $approval): RedirectResponse
    {
        $signers = $request->validate(['signers' => ['required', 'array', 'min:1', 'max:10'], 'signers.*' => ['required', 'string', 'max:120']])['signers'];

        return $this->paperAttempt(fn () => $this->paper->start($approval, $request->user(), $signers), 'Export it, have it signed, then upload the scan and tick who signed.');
    }

    /** SLD uploads the signed scan and ticks who has signed. */
    public function paperRecord(Request $request, Approval $approval): RedirectResponse
    {
        $data = $request->validate([
            'scan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            'signed' => ['present', 'array'], 'signed.*' => ['string', 'max:120'],
        ]);

        return $this->paperAttempt(fn () => $this->paper->record($approval, $request->user(), $request->file('scan'), $data['signed']), 'Saved.');
    }

    public function paperCancel(Request $request, Approval $approval): RedirectResponse
    {
        return $this->paperAttempt(fn () => $this->paper->cancel($approval, $request->user()), 'No paper signatures needed.');
    }

    public function paperScan(Request $request, Approval $approval): StreamedResponse
    {
        abort_unless($this->engine->canAct($approval, $request->user()) || $request->user()->can('view', $approval->document), 403);
        abort_unless($approval->paper_scan_path && Storage::disk('local')->exists($approval->paper_scan_path), 404);

        return Storage::disk('local')->response($approval->paper_scan_path);
    }

    private function paperAttempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
