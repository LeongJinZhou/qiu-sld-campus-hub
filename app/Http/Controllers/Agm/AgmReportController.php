<?php

namespace App\Http\Controllers\Agm;

use App\Http\Controllers\Controller;
use App\Jobs\DraftAgmReport;
use App\Models\Agm;
use App\Models\Document;
use App\Services\Agm\AgmPeriods;
use App\Services\Agm\AgmReportForm;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\DocumentPresenter;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** 01 AGM Report: editable objectives, speech, agenda and adjournment; everything else computed and locked. */
class AgmReportController extends Controller
{
    public function __construct(private readonly AgmReportForm $form, private readonly AgmPeriods $periods) {}

    public function edit(Agm $agm, DocumentPresenter $presenter, DocumentExporter $exporter): Response
    {
        Gate::authorize('viewInternal', $agm->club);
        $document = $agm->report()->firstOrFail();
        $data = $this->form->current($document);

        return Inertia::render('Agm/Report', [
            'agm' => ['id' => $agm->id, 'club' => $agm->club->only('slug', 'name')],
            'document' => [
                'id' => $document->id,
                'status' => $document->status->value,
                'editable' => $document->status->isEditable() && $agm->period->isOpen(),
                'approval' => $document->approvals()->exists() ? ['progress' => $presenter->progress($document), 'steps' => $presenter->present($document)['steps']] : null,
                'downloads' => $exporter->formats($document),
            ],
            'data' => $data,
            // A submitted report shows the snapshot it was signed with.
            'record' => $document->data()['computed'] ?? $this->form->computed($agm),
            'minObjectives' => AgmReportForm::MIN_OBJECTIVES,
            'ai' => Cache::get(DraftAgmReport::statusKey($document->id)),
            'aiAvailable' => (bool) config('services.anthropic.key'),
        ]);
    }

    public function save(Request $request, Agm $agm): RedirectResponse
    {
        $document = $this->editable($agm);
        $this->form->saveDraft($document, $this->validated($request), $request->user()->id);

        return back()->with('success', 'Draft saved.');
    }

    public function draft(Request $request, Agm $agm): RedirectResponse
    {
        $document = $this->editable($agm);
        $this->form->saveDraft($document, $this->validated($request), $request->user()->id);
        Cache::put(DraftAgmReport::statusKey($document->id), 'pending', now()->addMinutes(10));
        DraftAgmReport::dispatch($document->id, $request->user()->id);

        return back()->with('success', 'Drafting objectives, opening speech and agenda from this session…');
    }

    public function submit(Request $request, Agm $agm, ApprovalEngine $engine): RedirectResponse
    {
        $document = $this->editable($agm);
        $data = $this->validated($request);
        $signature = $request->validate(['signature' => ['required', 'string'], 'signature_strokes' => ['required', 'string', 'max:307200']]);
        try {
            $this->form->assertComplete($data);
            DB::transaction(function () use ($document, $data, $agm, $request, $engine, $signature): void {
                $this->form->saveDraft($document, $data, $request->user()->id);
                $engine->submit($document->fresh(), $request->user(), [...$data, 'computed' => $this->form->computed($agm)], $signature['signature'], $signature['signature_strokes']);
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'AGM Report signed and sent to the President.');
    }

    private function editable(Agm $agm): Document
    {
        Gate::authorize('viewInternal', $agm->club);
        $this->periods->assertOpen($agm->period);
        $document = $agm->report()->firstOrFail();
        abort_unless($document->status->isEditable(), 403, 'This report is in review and cannot be edited.');

        return $document;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'objectives' => ['present', 'array', 'max:15'],
            'objectives.*' => ['nullable', 'string', 'max:500'],
            'speech' => ['present', 'array', 'max:10'],
            'speech.*' => ['nullable', 'string', 'max:1000'],
            'agenda' => ['present', 'array', 'max:30'],
            'agenda.*.start' => ['nullable', 'string', 'max:20'],
            'agenda.*.end' => ['nullable', 'string', 'max:20'],
            'agenda.*.item' => ['nullable', 'string', 'max:255'],
            'adjournment' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            'objectives' => array_map(fn ($v) => (string) $v, $data['objectives']),
            'speech' => array_map(fn ($v) => (string) $v, $data['speech']),
            'agenda' => array_map(fn ($a) => ['start' => (string) ($a['start'] ?? ''), 'end' => (string) ($a['end'] ?? ''), 'item' => (string) ($a['item'] ?? '')], $data['agenda']),
            'adjournment' => (string) ($data['adjournment'] ?? ''),
        ];
    }
}
