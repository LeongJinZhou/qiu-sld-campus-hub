<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Jobs\DraftMinutes;
use App\Models\Club;
use App\Models\Meeting;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\DocumentPresenter;
use App\Services\Google\CalendarMeetService;
use App\Services\Meetings\Meetings;
use App\Services\Meetings\MinutesForm;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function __construct(private readonly Meetings $meetings, private readonly MinutesForm $form) {}

    public function index(Request $request, Club $club, CalendarMeetService $calendar, DocumentPresenter $presenter): Response
    {
        Gate::authorize('viewInternal', $club);

        return Inertia::render('Meetings/Index', [
            'club' => $club->only('slug', 'name'),
            'calendarConnected' => $calendar->isConnected($request->user()),
            'kinds' => Meeting::KINDS,
            'meetings' => $club->meetings()->with('minutes.approvals')->orderByDesc('starts_at')->limit(50)->get()->map(fn (Meeting $m) => [
                'id' => $m->id, 'title' => $m->title, 'kind' => $m->kind, 'starts_at' => $m->starts_at->toIso8601String(),
                'venue' => $m->venue, 'meet_link' => $m->meet_link, 'upcoming' => $m->ends_at->isFuture(),
                'minutes' => $m->minutes ? $presenter->progress($m->minutes) : null,
            ]),
        ]);
    }

    public function store(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('viewInternal', $club);
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', array_keys(Meeting::KINDS))],
            'title' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'venue' => ['nullable', 'required_unless:online,true', 'string', 'max:150'],
            'online' => ['boolean'],
        ]);

        try {
            $meeting = $this->meetings->create($club, $request->user(), [...$data, 'online' => $request->boolean('online')]);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('meetings.show', $meeting)->with('success', $meeting->meet_link ? 'Meeting created — Meet link sent to the committee.' : 'Meeting created and the committee notified.');
    }

    public function show(Request $request, Meeting $meeting, DocumentPresenter $presenter, DocumentExporter $exporter, ApprovalEngine $engine): Response
    {
        Gate::authorize('viewInternal', $meeting->club);
        $document = $meeting->minutes()->firstOrFail();
        $present = $meeting->attendees()->wherePivot('present', true)->pluck('users.id')->all();

        return Inertia::render('Meetings/Show', [
            'meeting' => [
                'id' => $meeting->id, 'title' => $meeting->title, 'kind' => $meeting->kind, 'kind_label' => Meeting::KINDS[$meeting->kind],
                'starts_at' => $meeting->starts_at->toIso8601String(), 'ends_at' => $meeting->ends_at->toIso8601String(),
                'venue' => $meeting->venue, 'meet_link' => $meeting->meet_link, 'club' => $meeting->club->only('slug', 'name'),
                'hours' => round($meeting->starts_at->diffInMinutes($meeting->ends_at, absolute: true) / 60, 1),
            ],
            'people' => $this->meetings->committee($meeting->club)
                ->merge($meeting->club->memberships()->whereIn('status', ['active', 'grace'])->with('user')->get()->pluck('user'))
                ->unique('id')->sortBy('name')->values()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'photo_url' => $u->photoUrl()]),
            'present' => $present,
            'minutes' => [
                'id' => $document->id,
                'status' => $document->status->value,
                'editable' => $document->status->isEditable(),
                'data' => $this->form->current($document),
                'approval' => $document->approvals()->exists() ? ['progress' => $presenter->progress($document), 'steps' => $presenter->present($document)['steps']] : null,
                'downloads' => $exporter->formats($document),
                'ai' => Cache::get(DraftMinutes::statusKey($document->id)),
                'ai_available' => (bool) config('services.anthropic.key'),
            ],
        ]);
    }

    public function attendance(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('viewInternal', $meeting->club);
        $data = $request->validate(['present' => ['present', 'array'], 'present.*' => ['integer']]);
        $this->meetings->recordAttendance($meeting, array_map('intval', $data['present']));

        return back()->with('success', 'Attendance saved.');
    }

    public function save(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('viewInternal', $meeting->club);
        $document = $meeting->minutes()->firstOrFail();
        if (! $document->status->isEditable()) {
            return back()->with('error', 'These minutes are in review and cannot be edited.');
        }
        $this->form->saveDraft($document, $this->validated($request), $request->user()->id);

        return back()->with('success', 'Draft saved.');
    }

    public function draft(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('viewInternal', $meeting->club);
        $document = $meeting->minutes()->firstOrFail();
        $this->form->saveDraft($document, $this->validated($request), $request->user()->id);
        Cache::put(DraftMinutes::statusKey($document->id), 'pending', now()->addMinutes(10));
        DraftMinutes::dispatch($document->id, $request->user()->id);

        return back()->with('success', 'Drafting the minutes from your notes…');
    }

    public function submit(Request $request, Meeting $meeting, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('viewInternal', $meeting->club);
        $document = $meeting->minutes()->firstOrFail();
        $data = $this->validated($request, submitting: true);
        $signature = $request->validate(['signature' => ['required', 'string'], 'signature_strokes' => ['required', 'string', 'max:307200']]);

        try {
            $this->meetings->assertCanSubmit($meeting);
            DB::transaction(function () use ($document, $data, $request, $engine, $signature): void {
                $this->form->saveDraft($document, $data, $request->user()->id);
                $engine->submit($document->fresh(), $request->user(), $data, $signature['signature'], $signature['signature_strokes']);
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Minutes signed and sent to the President.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $submitting = false): array
    {
        $required = $submitting ? 'required' : 'nullable';
        $data = $request->validate([
            'purpose' => [$required, 'string', 'max:500'],
            'summary' => ['present', 'array', 'max:30'],
            'summary.*' => [$required, 'string', 'max:500'],
            'actions' => ['present', 'array', 'max:30'],
            'actions.*.task' => ['required', 'string', 'max:255'],
            'actions.*.pic' => ['required', 'string', 'max:150'],
            'actions.*.deadline' => ['nullable', 'string', 'max:50'],
            'next_date' => ['nullable', 'date'],
            'next_time' => ['nullable', 'string', 'max:20'],
            'next_venue' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        return [...array_fill_keys(['purpose', 'next_date', 'next_time', 'next_venue', 'notes'], ''), ...array_map(fn ($v) => $v ?? '', $data)];
    }
}
