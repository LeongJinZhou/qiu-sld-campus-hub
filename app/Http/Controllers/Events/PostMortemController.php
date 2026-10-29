<?php

namespace App\Http\Controllers\Events;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SuggestPostMortem;
use App\Models\Event;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\DocumentPresenter;
use App\Services\Events\PostMortemForm;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PostMortemController extends Controller
{
    public function __construct(private readonly PostMortemForm $form) {}

    public function show(Request $request, Event $event, DocumentPresenter $presenter, DocumentExporter $exporter): Response
    {
        Gate::authorize('organises', $event);
        abort_unless($event->status === DocumentStatus::Approved, 404);
        $document = $this->form->document($event, $request->user());

        return Inertia::render('Events/PostMortem', [
            'event' => $event->only('slug', 'name'),
            'header' => $this->form->header($event),
            'areas' => PostMortemForm::AREAS,
            'document' => [
                'id' => $document->id,
                'status' => $document->status->value,
                'editable' => $document->status->isEditable(),
                'data' => $this->form->current($document),
                'approval' => $document->approvals()->exists() ? ['progress' => $presenter->progress($document), 'steps' => $presenter->present($document)['steps']] : null,
                'downloads' => $exporter->formats($document),
                'ai' => Cache::get(SuggestPostMortem::statusKey($document->id)),
                'ai_available' => (bool) config('services.anthropic.key'),
            ],
        ]);
    }

    public function save(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        $document = $this->form->document($event, $request->user());
        if (! $document->status->isEditable()) {
            return back()->with('error', 'This post mortem is in review and cannot be edited.');
        }
        $this->form->save($document, $this->validated($request), $request->user()->id);

        return back()->with('success', 'Draft saved.');
    }

    public function suggest(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        $document = $this->form->document($event, $request->user());
        $this->form->save($document, $this->validated($request), $request->user()->id);
        Cache::put(SuggestPostMortem::statusKey($document->id), 'pending', now()->addMinutes(10));
        SuggestPostMortem::dispatch($document->id, $request->user()->id);

        return back()->with('success', 'Asking AI for improvement suggestions…');
    }

    public function submit(Request $request, Event $event, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('update', $event);
        $document = $this->form->document($event, $request->user());
        $data = $this->validated($request);
        $signature = $request->validate(['signature' => ['required', 'string'], 'signature_strokes' => ['required', 'string', 'max:307200']]);

        try {
            DB::transaction(function () use ($document, $data, $request, $engine, $signature, $event): void {
                $this->form->save($document, $data, $request->user()->id);
                $engine->submit($document->fresh(), $request->user(), $data, $signature['signature'], $signature['signature_strokes']);
                // Ticks the post-event checklist item.
                $event->documentItems()->where('kind', 'post_mortem')->update(['submitted_by' => $request->user()->id, 'submitted_at' => now()]);
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Post mortem signed and sent to your Club Advisor.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $keys = implode(',', array_keys(PostMortemForm::AREAS));
        $data = $request->validate([
            'rows' => ['required', 'array:'.$keys],
            'rows.*.well' => ['nullable', 'string', 'max:2000'],
            'rows.*.challenges' => ['nullable', 'string', 'max:2000'],
            'rows.*.improvement' => ['nullable', 'string', 'max:2000'],
            'recommendations' => ['present', 'array', 'max:10'],
            'recommendations.*' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'rows' => array_map(fn ($r) => ['well' => (string) ($r['well'] ?? ''), 'challenges' => (string) ($r['challenges'] ?? ''), 'improvement' => (string) ($r['improvement'] ?? '')], $data['rows']),
            'recommendations' => array_map(fn ($r) => (string) $r, $data['recommendations']),
        ];
    }
}
