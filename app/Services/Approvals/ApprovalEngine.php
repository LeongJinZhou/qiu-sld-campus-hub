<?php

namespace App\Services\Approvals;

use App\Enums\ApprovalDecision;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ScopeType;
use App\Events\DocumentApproved;
use App\Models\Approval;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use App\Services\Roles\CompulsoryPositionsCheck;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Ordered approval chains: Approve / Return (with comment) / Reject. Returned documents are edited and
 * resubmitted; earlier approvals survive unless the budget or dates changed. Dates are always server time.
 */
class ApprovalEngine
{
    public const STEP_WORKING_DAYS = 3;

    public function __construct(
        private readonly Chains $chains,
        private readonly ApproverResolver $approvers,
        private readonly SignatureStore $signatures,
        private readonly CompulsoryPositionsCheck $compulsory,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function submit(Document $document, User $user, array $data, string $signature, ?string $strokes = null): void
    {
        if (! $document->status->isEditable()) {
            throw new DomainException('This document is already in review.');
        }
        $this->compulsory->assertComplete(ScopeType::Club, $document->club_id, $document->session_id);
        if ($document->type === DocumentType::Proposal) {
            $this->compulsory->assertComplete(ScopeType::Event, (int) $document->event_id, null);
        }

        $chain = $this->chains->for($document);
        if (! $this->approvers->canAct($user, $document, $chain[0])) {
            throw new DomainException("Only the club's {$chain[0]->label()} can submit this document.");
        }

        DB::transaction(function () use ($document, $user, $data, $signature, $strokes, $chain): void {
            $previous = $document->latestVersion;
            $version = $document->versions()->create([
                'number' => ($previous->number ?? 0) + 1,
                'data' => $data,
                'created_by' => $user->id,
            ]);

            $restart = $document->approvals()->doesntExist()
                || $previous === null
                || $this->chains->isMaterialChange($previous->data, $data);

            if ($restart) {
                $document->approvals()->delete();
                foreach ($chain as $i => $role) {
                    $document->approvals()->create(['step_no' => $i + 1, 'role' => $role]);
                }
            } else {
                // Keep earlier approvals; reopen from the step that returned it.
                $document->approvals()->where('decision', '!=', ApprovalDecision::Approved)
                    ->update(['decision' => ApprovalDecision::Pending, 'comment' => null, 'decided_at' => null, 'approver_user_id' => null]);
            }

            $first = $document->approvals()->where('step_no', 1)->firstOrFail();
            $this->decide($first, $user, ApprovalDecision::Approved, null, $signature, $version->id, $strokes);

            $document->update(['status' => DocumentStatus::InReview, 'submitted_at' => now(), 'decided_at' => null]);
            $document->event?->update(['status' => DocumentStatus::InReview]);
            $this->audit->log($restart ? 'document.submitted' : 'document.resubmitted', $document, ['version' => $version->number]);
        });

        $this->advance($document->fresh());
    }

    public function approve(Approval $approval, User $user, string $signature, ?string $comment = null, ?string $strokes = null): void
    {
        $this->assertCanAct($approval, $user);

        DB::transaction(function () use ($approval, $user, $signature, $comment, $strokes): void {
            $this->decide($approval, $user, ApprovalDecision::Approved, $comment, $signature, $approval->document->latestVersion?->id, $strokes);
            $this->audit->log('approval.approved', $approval->document, ['step' => $approval->step_no, 'role' => $approval->role->value]);
        });

        $this->advance($approval->document->fresh());
    }

    public function returnToClub(Approval $approval, User $user, string $comment): void
    {
        $this->close($approval, $user, ApprovalDecision::Returned, DocumentStatus::Returned, $comment);
    }

    public function reject(Approval $approval, User $user, string $comment): void
    {
        $this->close($approval, $user, ApprovalDecision::Rejected, DocumentStatus::Rejected, $comment);
    }

    /** The step waiting for action, if the document is in review. */
    public function currentStep(Document $document): ?Approval
    {
        return $document->status === DocumentStatus::InReview
            ? $document->approvals()->where('decision', ApprovalDecision::Pending)->orderBy('step_no')->first()
            : null;
    }

    public function canAct(Approval $approval, User $user): bool
    {
        return $this->currentStep($approval->document)?->is($approval) === true
            && $this->approvers->canAct($user, $approval->document, $approval->role);
    }

    private function assertCanAct(Approval $approval, User $user): void
    {
        if (! $this->canAct($approval, $user)) {
            throw new DomainException('This step is not waiting for you.');
        }
    }

    private function close(Approval $approval, User $user, ApprovalDecision $decision, DocumentStatus $status, string $comment): void
    {
        $this->assertCanAct($approval, $user);
        if (trim($comment) === '') {
            throw new DomainException('Please add a comment for the club.');
        }

        $document = $approval->document;
        DB::transaction(function () use ($approval, $user, $decision, $status, $comment, $document): void {
            $this->decide($approval, $user, $decision, $comment);
            $document->update(['status' => $status, 'decided_at' => $status === DocumentStatus::Rejected ? now() : null]);
            $document->event?->update(['status' => $status]);
            $this->audit->log("approval.{$decision->value}", $document, ['step' => $approval->step_no, 'comment' => $comment]);
        });

        $verb = $decision === ApprovalDecision::Returned ? 'returned' : 'rejected';
        $document->creator->notify(new DocumentNotification(
            "{$document->type->label()} {$verb}",
            "{$user->name} ({$approval->role->label()}) {$verb} it: {$comment}",
            route('documents.show', $document),
        ));
    }

    private function decide(Approval $approval, User $user, ApprovalDecision $decision, ?string $comment, ?string $signature = null, ?int $versionId = null, ?string $strokes = null): void
    {
        $document = $approval->document;
        if ($signature !== null) {
            $approval->signature()->delete();
            $this->signatures->store($user, $signature, $this->approvers->positionFor($user, $document, $approval->role), [
                'document_id' => $document->id,
                'approval_id' => $approval->id,
                'document_version_id' => $versionId,
            ], $strokes);
        }
        $approval->update([
            'decision' => $decision,
            'approver_user_id' => $user->id,
            'comment' => $comment,
            'decided_at' => now(),
        ]);
    }

    /** Sets the next step's due date and notifies its approvers, or finishes the chain. */
    private function advance(Document $document): void
    {
        $next = $this->currentStep($document);
        if ($next === null) {
            $document->update(['status' => DocumentStatus::Approved, 'decided_at' => now()]);
            $this->audit->log('document.approved', $document);
            DocumentApproved::dispatch($document);
            $document->creator->notify(new DocumentNotification(
                "{$document->type->label()} approved",
                'All approvers have signed.',
                route('documents.show', $document),
            ));

            return;
        }

        $next->update(['due_at' => now()->addWeekdays(self::STEP_WORKING_DAYS)]);
        Notification::send($this->approvers->candidates($document, $next->role), new DocumentNotification(
            "{$document->type->label()} waiting for you",
            "{$document->club->name}: step {$next->step_no} ({$next->role->label()}). Due {$next->due_at->toFormattedDateString()}.",
            route('approvals.show', $next),
        ));
    }
}
