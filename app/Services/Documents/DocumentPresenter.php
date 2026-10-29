<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\Approval;
use App\Models\Document;
use App\Models\RoleAssignment;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Events\ProposalForm;
use App\Services\Events\ProposalRules;

/** Read-only view of a document, its data and its approval trail, for review and history pages. */
class DocumentPresenter
{
    public function __construct(
        private readonly ApprovalEngine $engine,
        private readonly ProposalForm $proposals,
        private readonly ProposalRules $rules,
    ) {}

    /** @return array<string, mixed> */
    public function present(Document $document): array
    {
        $document->loadMissing(['club', 'event', 'latestVersion', 'approvals.approver', 'approvals.signature']);
        $current = $this->engine->currentStep($document);
        $data = $document->data();

        return [
            'id' => $document->id,
            'type' => $document->type->value,
            'type_label' => $document->type->label(),
            'status' => $document->status->value,
            'version' => $document->latestVersion?->number,
            'club' => $document->club->only('slug', 'name'),
            'event' => $document->event?->only('slug', 'name'),
            'submitted_at' => $document->submitted_at?->toDayDateTimeString(),
            'data' => $data,
            'locked' => $document->type === DocumentType::Proposal ? $this->proposals->lockedFields($document) : [],
            'totals' => isset($data['budget']) ? $this->rules->budgetTotals($data['budget']) : null,
            'committee' => $document->event?->committee()->with('user')->get()->map(fn (RoleAssignment $a) => [
                'name' => $a->user->name, 'position' => $a->position, 'responsibility' => $a->responsibility,
            ]) ?? [],
            'options' => $document->type === DocumentType::Proposal
                ? ['natures' => ProposalForm::NATURES, 'sdgs' => ProposalForm::SDGS, 'fundSources' => ProposalForm::FUND_SOURCES]
                : [],
            'steps' => $document->approvals->map(fn (Approval $a) => [
                'id' => $a->id,
                'step_no' => $a->step_no,
                'role' => $a->role->label(),
                'decision' => $a->decision->value,
                'is_current' => $current?->is($a) === true,
                'approver' => $a->approver?->name,
                'position' => $a->signature?->position_at_time,
                'comment' => $a->comment,
                'decided_at' => $a->decided_at?->toDayDateTimeString(),
                'due_at' => $a->due_at?->toFormattedDateString(),
                'verification_code' => $a->signature?->verification_code,
            ]),
        ];
    }
}
