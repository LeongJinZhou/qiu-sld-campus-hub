<?php

namespace App\Http\Controllers\Events;

use App\Enums\ScopeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\ProposalRequest;
use App\Http\Requests\Social\StorePostRequest;
use App\Models\Club;
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
use App\Services\SldRules;
use App\Services\Uploads\ImageUpload;
use App\Support\QueryInput;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
        $data = $this->form->current($document);

        return Inertia::render('Events/Proposal', [
            'event' => $event->only('slug', 'name'),
            'documentId' => $document->id,
            'status' => $document->status->value,
            'editable' => $document->status->isEditable(),
            'approval' => $document->approvals()->exists() ? ['progress' => $presenter->progress($document), 'steps' => $presenter->present($document)['steps']] : null,
            'data' => $data,
            'revs' => $this->form->revisions($data),
            'lastEdit' => $this->lastEdit($document),
            'clubCommittee' => app(Meetings::class)->committee($event->club)->reject(fn ($u) => $u->is($user))->values()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'canDiscard' => $document->status->value === 'draft' && ! $document->versions()->where('number', '>', 0)->exists(),
            'locked' => [
                ...$this->form->lockedFields($document),
                'prepared_by' => $user->name,
            ],
            'committee' => $this->committee($event),
            'missingCommittee' => $compulsory->missingForEvent($event->id, ProposalForm::isWorkshop($data)),
            'missingClubCommittee' => $compulsory->missing(ScopeType::Club, $event->club_id, $event->session_id),
            'returnComment' => ($returned = $document->approvals()->whereIn('decision', ['returned', 'rejected'])->latest('decided_at')->first())?->comment,
            // The parts the advisor or SLD asked to fix, highlighted on the form until it is resubmitted.
            'returnSections' => in_array($document->status->value, ['returned', 'rejected'], true) ? ($returned?->sections ?? []) : [],
            'ccaLimit' => $this->rules->ccaLimit(),
            'imageBase' => Storage::disk('public')->url(''),
            // Partner clubs to pick from (any other partner can be typed in).
            'clubNames' => Club::where('id', '!=', $event->club_id)->orderBy('name')->pluck('name'),
            'options' => [
                'natures' => ProposalForm::NATURES,
                'sdgs' => ProposalForm::SDGS,
                'fundSources' => ProposalForm::FUND_SOURCES,
                'compulsoryPositions' => CompulsoryPositionsCheck::POSITIONS,
            ],
            'leadTime' => ['earliest' => $this->rules->earliestStart(now())->toDateString(), 'days' => app(SldRules::class)->get('proposal_lead_days')],
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

    public function submit(ProposalRequest $request, Event $event, ApprovalEngine $engine, ProposalDrafts $drafts): RedirectResponse
    {
        $document = $event->proposal()->firstOrFail();
        $data = $request->proposalData();
        if (! $document->status->isEditable()) {
            return back()->with('error', 'This proposal is in review or decided and cannot be changed.');
        }

        try {
            $this->rules->assertLeadTime(CarbonImmutable::parse($data['starts_at']), now(), $data['late_reason'] ?? null);
            // Draft + submission succeed or fail together, so a refused submission never changes the event.
            DB::transaction(function () use ($document, $data, $request, $engine): void {
                $this->form->saveDraft($document, $data, $request->user()->id);
                $engine->submit($document->fresh(), $request->user(), $data, $request->validated('signature'), $request->validated('signature_strokes'));
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        $drafts->submitted($event, $request->user());

        return to_route('documents.show', $document)->with('success', 'Proposal submitted to your advisor.');
    }

    /**
     * Live co-editing: what the shared draft looks like now, who else has it open and which part they are on.
     * Polled every few seconds; also marks the caller as present (and in which section).
     */
    public function live(Request $request, Event $event, CompulsoryPositionsCheck $compulsory): JsonResponse
    {
        Gate::authorize('update', $event);
        $document = $event->proposal()->firstOrFail();
        $user = $request->user();
        $section = QueryInput::text($request, 'section', 40);
        $key = "proposal-presence:{$document->id}";
        $present = collect(Cache::get($key, []))->filter(fn (array $p) => $p['at'] > now()->subSeconds(20)->getTimestamp());
        $present[$user->id] = ['name' => $user->name, 'photo_url' => $user->photoUrl(), 'section' => $section ?: null, 'at' => now()->getTimestamp()];
        Cache::put($key, $present->all(), now()->addMinutes(5));

        return response()->json([
            'status' => $document->status->value,
            'data' => $data = $this->form->current($document),
            'revs' => $this->form->revisions($data),
            'lastEdit' => $this->lastEdit($document),
            'editors' => $present->except($user->id)->map(fn (array $p, int $id) => ['id' => $id, ...collect($p)->except('at')->all()])->values(),
            'committee' => $this->committee($event),
            'missingCommittee' => $compulsory->missingForEvent($event->id, ProposalForm::isWorkshop($data)),
        ]);
    }

    /**
     * Autosave of only the parts this person changed, merged into the latest shared draft, so two committee
     * members typing in different sections never overwrite each other.
     */
    public function patch(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);
        $document = $event->proposal()->firstOrFail();
        if (! $document->status->isEditable()) {
            return response()->json(['message' => 'This proposal is in review and cannot be edited.'], 409);
        }
        $changes = collect((array) $request->input('changes', []))->only(array_keys($this->form->defaults($event)))->all();
        // Half-typed work must still save (an empty new budget line, a time not finished yet): only the changed parts
        // are checked, and only for their type and size; completeness is checked when someone submits.
        $rules = collect((new ProposalRequest)->rules())
            ->filter(fn ($r, string $field) => array_key_exists(explode('.', $field)[0], $changes))
            ->map(fn (array $r) => array_values(array_filter(array_map(fn ($rule) => $rule === 'required' ? 'nullable' : $rule, $r),
                fn ($rule) => ! is_string($rule) || ! str_starts_with($rule, 'date_format') && ! str_starts_with($rule, 'after'))))->all();
        $validator = Validator::make($changes, $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()->toArray()], 422);
        }
        $sent = (array) $request->input('revs', []);

        // Read, compare and write under one lock, so two saves at the same instant cannot both pass the check.
        return DB::transaction(function () use ($document, $changes, $sent, $request): JsonResponse {
            Document::whereKey($document->id)->lockForUpdate()->first();
            $current = $this->form->current($document);
            $revs = $this->form->revisions($current);
            $conflicts = array_values(array_filter(array_keys($changes), fn (string $key) => isset($sent[$key]) && $sent[$key] !== ($revs[$key] ?? null)));
            if ($conflicts) {
                // Someone saved this part after this person last saw it: show their version rather than overwrite it.
                return response()->json(['message' => 'Another user changed this section at the same time. Please refresh and try again.', 'conflicts' => $conflicts, 'data' => Arr::only($current, $conflicts), 'revs' => $revs], 409);
            }
            $merged = array_replace($current, $changes);
            $this->form->saveDraft($document, $merged, $request->user()->id);

            return response()->json(['lastEdit' => $this->lastEdit($document), 'revs' => $this->form->revisions($merged)]);
        });
    }

    /** A speaker photo or a picture for an added part: cleaned (GD re-encode) and stored; the form keeps its path. */
    public function image(Request $request, Event $event, ImageUpload $images): JsonResponse
    {
        Gate::authorize('update', $event);
        $request->validate(['image' => ['required', ...StorePostRequest::IMAGE_RULES]]);
        $path = $images->store($request->file('image'), "proposal-images/{$event->id}");

        return response()->json(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function committee(Event $event)
    {
        return $event->committee()->with('user.student')->get()->map(fn (RoleAssignment $a) => [
            'id' => $a->id,
            'position' => $a->position,
            'is_compulsory' => $a->is_compulsory,
            'name' => $a->user->name,
            'student_number' => $a->user->student?->student_number,
            'responsibility' => $a->responsibility,
        ]);
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
