<?php

namespace App\Services\Approvals;

use App\Enums\ApprovalDecision;
use App\Enums\ChainRole;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ScopeType;
use App\Events\DocumentApproved;
use App\Models\Approval;
use App\Models\Document;
use App\Models\Signature;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use App\Services\Events\ProposalForm;
use App\Services\Events\ProposalRules;
use App\Services\Reports\EvidencePhotos;
use App\Services\Roles\CompulsoryPositionsCheck;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Ordered approval chains: Approve / Return (with comment) / Reject. Returned documents are edited and
 * resubmitted. Any change to the signed content restarts the review (see Chains::isMaterialChange); an unchanged
 * resubmission keeps earlier approvals. Dates are always server time.
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
            $this->compulsory->assertEventComplete((int) $document->event_id, ProposalForm::isWorkshop($data));
        }

        // Photo evidence (SLD minimum): a weekly activity report and the AGM report.
        $document->loadMissing('meeting', 'agm');
        if ($document->type === DocumentType::Minutes && $document->meeting?->kind === 'activity') {
            app(EvidencePhotos::class)->assertEnough($document->meeting, 'this weekly activity report');
        }
        if ($document->type === DocumentType::AgmReport && $document->agm) {
            app(EvidencePhotos::class)->assertEnough($document->agm, 'the AGM');
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

            // A rejected document starts again from the first approver: everyone signs again. Earlier signatures stay
            // on record with the version they signed, but no longer count for the new submission.
            $wasRejected = $document->status === DocumentStatus::Rejected;
            $restart = $wasRejected
                || $document->approvals()->doesntExist()
                || $previous === null
                || $this->chains->isMaterialChange($previous->data, $data);

            if ($restart) {
                if ($wasRejected) {
                    Signature::where('document_id', $document->id)->whereNotNull('approval_id')->update(['approval_id' => null]);
                }
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
            $this->syncEventStatus($document, DocumentStatus::InReview);
            $this->audit->log($restart ? 'document.submitted' : 'document.resubmitted', $document, ['version' => $version->number]);
        });

        $this->advance($document->fresh());
    }

    public function approve(Approval $approval, User $user, string $signature, ?string $comment = null, ?string $strokes = null, bool $acceptExceptions = false): void
    {
        $this->assertCanAct($approval, $user);
        // SLD's final approval of a proposal with exceptions (late, deficit) must approve them explicitly, on record.
        $exceptions = $this->exceptions($approval->document);
        if ($exceptions !== [] && $approval->role === ChainRole::SldOfficer) {
            if (! $acceptExceptions) {
                throw new DomainException('This proposal has exceptions (late submission or budget deficit). Approve them explicitly, or return it.');
            }
            $comment = trim(($comment ? "{$comment}\n" : '').'Exceptions approved: '.implode('; ', array_map(fn (array $e) => "{$e['label']} (reason: {$e['reason']})", $exceptions)));
        }

        // Higher authorities asked to sign on paper: every one ticked and the signed scan on file, recorded with the approval.
        if ($blocker = PaperSignoff::blocker($approval)) {
            throw new DomainException($blocker);
        }
        if ($approval->paper) {
            $comment = trim(($comment ? "{$comment}\n" : '').'Signed on paper by: '.collect($approval->paper['signers'])->pluck('label')->implode(', ').' (signed scan on file).');
        }

        DB::transaction(function () use ($approval, $user, $signature, $comment, $strokes): void {
            $this->decide($approval, $user, ApprovalDecision::Approved, $comment, $signature, $approval->document->latestVersion?->id, $strokes);
            $this->audit->log('approval.approved', $approval->document, ['step' => $approval->step_no, 'role' => $approval->role->value]);
        });

        $this->advance($approval->document->fresh());
    }

    /** @param list<array{key: string, note: string}> $sections the parts to fix, highlighted for the club */
    public function returnToClub(Approval $approval, User $user, string $comment, array $sections = []): void
    {
        if (trim($comment) === '' && $sections === []) {
            throw new DomainException('State what needs changing: add a comment or select the parts to fix.');
        }
        if ($comment === '') {
            $comment = 'Please fix: '.implode('; ', array_map(fn (array $s) => "{$s['key']}: {$s['note']}", $sections));
        }
        $this->close($approval, $user, ApprovalDecision::Returned, DocumentStatus::Returned, $comment);
        $approval->update(['sections' => $sections ?: null]);
    }

    /** @param list<array{key: string, note: string}> $sections the parts that are wrong, highlighted for the club */
    public function reject(Approval $approval, User $user, string $comment, array $sections = []): void
    {
        if (trim($comment) === '' && $sections !== []) {
            $comment = 'Rejected: '.implode('; ', array_map(fn (array $s) => "{$s['key']}: {$s['note']}", $sections));
        }
        $this->close($approval, $user, ApprovalDecision::Rejected, DocumentStatus::Rejected, $comment);
        $approval->update(['sections' => $sections ?: null]);
    }

    /** @return list<array{kind: string, label: string, reason: string}> exceptions on a submitted proposal */
    public function exceptions(Document $document): array
    {
        if ($document->type !== DocumentType::Proposal || ! $document->latestVersion) {
            return [];
        }

        return app(ProposalRules::class)->exceptions($document->latestVersion->data, $document->submitted_at ?? now());
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

    /** An event's status follows its proposal only; other documents about the event leave it alone. */
    private function syncEventStatus(Document $document, DocumentStatus $status): void
    {
        if ($document->type === DocumentType::Proposal) {
            $document->event?->update(['status' => $status]);
        }
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
            $this->syncEventStatus($document, $status);
            $this->audit->log("approval.{$decision->value}", $document, ['step' => $approval->step_no, 'comment' => $comment]);
        });

        $verb = $decision === ApprovalDecision::Returned ? 'returned' : 'rejected';
        $document->creator->notify(new DocumentNotification(
            "{$document->type->label()} {$verb}",
            "{$user->name} ({$approval->role->label()}) {$verb} it: {$comment}".($decision === ApprovalDecision::Rejected ? ' You can correct it and submit again; everyone signs again.' : ''),
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
            "{$document->club->name}: step {$next->step_no} ({$next->role->label()}). Due {$next->due_at->format('j M Y')}.",
            route('approvals.show', $next),
        ));
    }
}
