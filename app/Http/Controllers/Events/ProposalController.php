<?php

namespace App\Http\Controllers\Events;

use App\Enums\ScopeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ProposalRequest;
use App\Models\Event;
use App\Models\RoleAssignment;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Events\ProposalForm;
use App\Services\Events\ProposalRules;
use App\Services\Roles\CompulsoryPositionsCheck;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function __construct(private readonly ProposalForm $form, private readonly ProposalRules $rules) {}

    public function edit(Request $request, Event $event, CompulsoryPositionsCheck $compulsory): Response
    {
        Gate::authorize('update', $event);
        $document = $event->proposal()->firstOrFail();
        $user = $request->user();

        return Inertia::render('Events/Proposal', [
            'event' => $event->only('slug', 'name'),
            'documentId' => $document->id,
            'status' => $document->status->value,
            'editable' => $document->status->isEditable(),
            'data' => $this->form->current($document),
            'locked' => [
                ...$this->form->lockedFields($document),
                'prepared_by' => $user->name,
            ],
            'committee' => $event->committee()->with('user.student')->get()->map(fn (RoleAssignment $a) => [
                'id' => $a->id,
                'position' => $a->position,
                'is_compulsory' => $a->is_compulsory,
                'name' => $a->user->name,
                'student_number' => $a->user->student?->student_number,
                'responsibility' => $a->responsibility,
            ]),
            'missingCommittee' => $compulsory->missing(ScopeType::Event, $event->id, null),
            'missingClubCommittee' => $compulsory->missing(ScopeType::Club, $event->club_id, $event->session_id),
            'returnComment' => $document->approvals()->where('decision', 'returned')->latest('decided_at')->value('comment'),
            'options' => [
                'natures' => ProposalForm::NATURES,
                'sdgs' => ProposalForm::SDGS,
                'fundSources' => ProposalForm::FUND_SOURCES,
                'compulsoryPositions' => CompulsoryPositionsCheck::POSITIONS,
            ],
            'leadTime' => [
                'threshold' => ProposalRules::LARGE_EVENT_PARTICIPANTS,
                'small' => $this->rules->earliestStart(1, now())->toDateString(),
                'large' => $this->rules->earliestStart(ProposalRules::LARGE_EVENT_PARTICIPANTS, now())->toDateString(),
            ],
        ]);
    }

    public function update(ProposalRequest $request, Event $event): RedirectResponse
    {
        $document = $event->proposal()->firstOrFail();
        if (! $document->status->isEditable()) {
            return back()->with('error', 'This proposal is in review and cannot be edited.');
        }
        $this->form->saveDraft($document, $request->proposalData(), $request->user()->id);

        return back()->with('success', 'Draft saved.');
    }

    public function submit(ProposalRequest $request, Event $event, ApprovalEngine $engine): RedirectResponse
    {
        $document = $event->proposal()->firstOrFail();
        $data = $request->proposalData();

        try {
            $this->rules->assertLeadTime((int) $data['expected_participants'], CarbonImmutable::parse($data['starts_at']), now());
            $this->form->saveDraft($document, $data, $request->user()->id);
            $engine->submit($document->fresh(), $request->user(), $data, $request->validated('signature'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('documents.show', $document)->with('success', 'Proposal submitted to your advisor.');
    }
}
