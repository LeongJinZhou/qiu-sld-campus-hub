<?php

namespace App\Http\Controllers\Events;

use App\Enums\ScopeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ProposalRequest;
use App\Models\Document;
use App\Models\Event;
use App\Models\RoleAssignment;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocumentPresenter;
use App\Services\Events\ProposalDrafts;
use App\Services\Events\ProposalForm;
use App\Services\Events\ProposalRules;
use App\Services\Meetings\Meetings;
use App\Services\Roles\CompulsoryPositionsCheck;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function __construct(private readonly ProposalForm $form, private readonly ProposalRules $rules) {}

    public function edit(Request $request, Event $event, CompulsoryPositionsCheck $compulsory, DocumentPresenter $presenter): Response
    {
        Gate::authorize('update', $event);
        $document = $event->proposal()->firstOrFail();
        $user = $request->user();

        return Inertia::render('Events/Proposal', [
            'event' => $event->only('slug', 'name'),
            'documentId' => $document->id,
            'status' => $document->status->value,
            'editable' => $document->status->isEditable(),
            'approval' => $document->approvals()->exists() ? ['progress' => $presenter->progress($document), 'steps' => $presenter->present($document)['steps']] : null,
            'data' => $this->form->current($document),
            'lastEdit' => $this->lastEdit($document),
            'clubCommittee' => app(Meetings::class)->committee($event->club)->reject(fn ($u) => $u->is($user))->values()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'canDiscard' => $document->status->value === 'draft' && ! $document->versions()->where('number', '>', 0)->exists(),
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

        // Autosave runs every few seconds while typing: no message, just the "Saved" indicator.
        return $request->boolean('autosave') ? back() : back()->with('success', 'Draft saved.');
    }

    public function submit(ProposalRequest $request, Event $event, ApprovalEngine $engine): RedirectResponse
    {
        $document = $event->proposal()->firstOrFail();
        $data = $request->proposalData();
        if (! $document->status->isEditable()) {
            return back()->with('error', 'This proposal is in review or decided and cannot be changed.');
        }

        try {
            $this->rules->assertLeadTime((int) $data['expected_participants'], CarbonImmutable::parse($data['starts_at']), now());
            // Draft + submission succeed or fail together, so a refused submission never changes the event.
            DB::transaction(function () use ($document, $data, $request, $engine): void {
                $this->form->saveDraft($document, $data, $request->user()->id);
                $engine->submit($document->fresh(), $request->user(), $data, $request->validated('signature'), $request->validated('signature_strokes'));
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('documents.show', $document)->with('success', 'Proposal submitted to your advisor.');
    }

    /** Ask committee members to review and submit (customisable: pick who, add a note). */
    public function askToSubmit(Request $request, Event $event, ProposalDrafts $drafts): RedirectResponse
    {
        Gate::authorize('update', $event);
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1'], 'user_ids.*' => ['integer'], 'note' => ['nullable', 'string', 'max:300']]);
        $sent = $drafts->askToSubmit($event, $request->user(), array_map('intval', $data['user_ids']), $data['note'] ?? null);

        return back()->with('success', $sent === 1 ? 'They have been notified.' : "{$sent} committee members notified.");
    }

    public function discard(Event $event, ProposalDrafts $drafts): RedirectResponse
    {
        Gate::authorize('update', $event);
        $club = $event->club;
        try {
            $drafts->discard($event);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('clubs.show', $club)->with('success', 'Draft discarded.');
    }

    /** @return array{by: ?string, at: ?string} who saved the shared draft last, and when */
    private function lastEdit(Document $document): array
    {
        $draft = $document->versions()->where('number', 0)->with('author')->first();

        return ['by' => $draft?->author?->name, 'at' => $draft?->created_at?->toIso8601String()];
    }
}
