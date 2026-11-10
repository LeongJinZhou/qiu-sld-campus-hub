<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Jobs\DraftMinutes;
use App\Models\Club;
use App\Models\Meeting;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Documents\DocumentExporter;
use App\Services\Documents\DocumentPresenter;
use App\Services\Documents\PdfConverter;
use App\Services\Events\AttendanceQr;
use App\Services\Events\CustomAttendanceQr;
use App\Services\Google\CalendarMeetService;
use App\Services\Meetings\MeetingAttendance;
use App\Services\Meetings\Meetings;
use App\Services\Meetings\MinutesForm;
use App\Services\QrCodes;
use App\Services\Reports\EvidencePhotos;
use App\Support\QueryInput;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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
            'audiences' => Meeting::AUDIENCES,
            'meetings' => $club->meetings()->with('minutes.approvals')->orderByDesc('starts_at')->limit(50)->get()->map(fn (Meeting $m) => [
                'id' => $m->id, 'title' => $m->title, 'kind' => $m->kind, 'starts_at' => $m->starts_at->toIso8601String(),
                'venue' => $m->venue, 'meet_link' => $m->meet_link, 'upcoming' => $m->ends_at->isFuture(),
                'audience' => $m->kind === 'activity' ? Meeting::AUDIENCES[$m->audience] ?? null : Meeting::AUDIENCES['committee'], 'pax' => $m->pax,
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
            // A weekly activity always has a place and an expected head count; a committee meeting may be online only.
            'venue' => ['nullable', 'required_if:kind,activity', 'required_unless:online,true', 'string', 'max:150'],
            'online' => ['boolean'],
            'audience' => ['nullable', 'required_if:kind,activity', 'in:'.implode(',', array_keys(Meeting::AUDIENCES))],
            'pax' => ['nullable', 'required_if:kind,activity', 'integer', 'min:1', 'max:5000'],
        ], ['venue.required_if' => 'Enter the location of the weekly activity.', 'pax.required_if' => 'Enter the expected number of participants.', 'audience.required_if' => 'Choose who is invited.']);

        try {
            $meeting = $this->meetings->create($club, $request->user(), [...$data, 'online' => $request->boolean('online')]);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $who = $meeting->kind === 'activity' && $meeting->audience === 'members' ? 'every member' : 'the committee and advisors';

        return to_route('meetings.show', $meeting)->with('success', $meeting->meet_link ? "Meeting created — Meet link sent to {$who}." : "Created and {$who} notified.");
    }

    public function show(Request $request, Meeting $meeting, DocumentPresenter $presenter, DocumentExporter $exporter, ApprovalEngine $engine, AttendanceQr $qr, QrCodes $codes, CustomAttendanceQr $custom, MeetingAttendance $checklist): Response
    {
        Gate::authorize('viewInternal', $meeting->club);
        $document = $meeting->minutes()->firstOrFail();
        $rows = $meeting->attendees()->get();
        $present = $rows->filter(fn ($u) => (bool) $u->pivot->present)->pluck('id')->values()->all();
        $time = fn (?string $at): ?string => $at ? Carbon::parse($at)->toIso8601String() : null;
        $open = now()->between($meeting->starts_at->copy()->subMinutes(Meeting::CHECK_IN_EARLY_MINUTES), $meeting->ends_at->copy()->addMinutes(Meeting::CHECK_OUT_LATE_MINUTES));

        return Inertia::render('Meetings/Show', [
            'meeting' => [
                'id' => $meeting->id, 'title' => $meeting->title, 'kind' => $meeting->kind, 'kind_label' => Meeting::KINDS[$meeting->kind],
                'starts_at' => $meeting->starts_at->toIso8601String(), 'ends_at' => $meeting->ends_at->toIso8601String(),
                'venue' => $meeting->venue, 'meet_link' => $meeting->meet_link, 'club' => $meeting->club->only('slug', 'name'),
                'audience' => Meeting::AUDIENCES[$meeting->kind === 'activity' ? $meeting->audience : 'committee'] ?? null, 'pax' => $meeting->pax,
                'hours' => round($meeting->starts_at->diffInMinutes($meeting->ends_at, absolute: true) / 60, 1),
            ],
            'evidence' => $meeting->kind === 'activity' ? app(EvidencePhotos::class)->panel($meeting) : null,
            'people' => $this->meetings->invitees($meeting)->sortBy('name')->values()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'photo_url' => $u->photoUrl()]),
            'present' => $present,
            'checkins' => $rows->filter(fn ($u) => $u->pivot->checked_in_at || $u->pivot->method)->mapWithKeys(fn ($u) => [$u->id => [
                'in' => $time($u->pivot->checked_in_at), 'out' => $time($u->pivot->checked_out_at), 'method' => $u->pivot->method,
            ]]),
            // The rotating check-in / check-out codes are shown on the organiser's screen while the meeting is on.
            'canManage' => Gate::allows('publish', $meeting->club),
            // The committee's own uploaded check-in / check-out QR images, if any.
            'customQr' => Gate::allows('publish', $meeting->club) ? $custom->urls($meeting) : null,
            'checklist' => $checklist->rows($meeting),
            'tab' => QueryInput::oneOf($request, 'tab', ['attendance', 'minutes']) ?? 'attendance',
            'qr' => $open && Gate::allows('publish', $meeting->club) ? [
                'in' => $codes->svg(route('meetings.attend', ['meeting' => $meeting, 'action' => 'in', 'code' => $qr->code($meeting, 'in')])),
                'out' => $codes->svg(route('meetings.attend', ['meeting' => $meeting, 'action' => 'out', 'code' => $qr->code($meeting, 'out')])),
                'seconds' => $qr->seconds($meeting), 'seconds_left' => $qr->secondsLeft($meeting),
            ] : null,
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

    /** A committee member, advisor or (for an all-members activity) a member scanned the meeting QR with their phone. */
    public function attend(Request $request, Meeting $meeting, AttendanceQr $qr): RedirectResponse
    {
        $action = QueryInput::text($request, 'action', 3);
        $back = to_route('clubs.show', $meeting->club);
        if (! $qr->isValid($meeting, $action, QueryInput::text($request, 'code', 100))) {
            return $back->with('error', 'This QR code has expired. Scan the code on screen again.');
        }
        try {
            $message = $this->meetings->clock($meeting, $request->user(), $action);
        } catch (DomainException $e) {
            return $back->with('error', $e->getMessage());
        }

        return $back->with('success', $message);
    }

    /** The committee uploads (or removes) its own check-in and check-out QR images. */
    public function customQr(Request $request, Meeting $meeting, CustomAttendanceQr $custom): RedirectResponse
    {
        Gate::authorize('publish', $meeting->club);
        $data = $request->validate([
            'in' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'out' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_in' => ['boolean'], 'remove_out' => ['boolean'],
        ]);
        $custom->update($meeting, [...$data, 'in' => $request->file('in'), 'out' => $request->file('out')]);

        return back()->with('success', 'Attendance QR codes updated.');
    }

    /** The attendance checklist as Excel or PDF, for the committee, advisor and SLD. */
    public function attendanceExport(Meeting $meeting, string $format, MeetingAttendance $checklist, PdfConverter $pdf): BinaryFileResponse
    {
        Gate::authorize('viewInternal', $meeting->club);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $file = $checklist->export($meeting);
        if ($format === 'pdf') {
            $converted = $pdf->convert($file);
            @unlink($file);
            $file = $converted;
        }

        return response()->download($file, $this->fileName($meeting, 'Attendance').".{$format}")->deleteFileAfterSend();
    }

    /** Everything on file for one meeting in a ZIP: the minutes (Word and PDF) and the attendance checklist (Excel and PDF). */
    public function package(Meeting $meeting, DocumentExporter $exporter, MeetingAttendance $checklist, PdfConverter $pdf): BinaryFileResponse
    {
        Gate::authorize('viewInternal', $meeting->club);
        $zipPath = storage_path('app/private/generated/meeting-'.$meeting->id.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)).'.zip');
        @mkdir(dirname($zipPath), 0775, true);
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $cleanup = [];
        $sheet = $checklist->export($meeting);
        $cleanup[] = $sheet;
        $zip->addFromString($this->fileName($meeting, 'Attendance').'.xlsx', (string) file_get_contents($sheet));
        $minutes = $meeting->minutes;
        foreach ($minutes ? $exporter->formats($minutes) : [] as $format) {
            try {
                $file = $exporter->export($minutes, $format);
                $cleanup[] = $file;
                $zip->addFromString($this->fileName($meeting, 'Minutes').".{$format}", (string) file_get_contents($file));
            } catch (\Throwable) {
                // PDF needs LibreOffice; the Word original is still in the package.
            }
        }
        try {
            $sheetPdf = $pdf->convert($sheet);
            $cleanup[] = $sheetPdf;
            $zip->addFromString($this->fileName($meeting, 'Attendance').'.pdf', (string) file_get_contents($sheetPdf));
        } catch (\Throwable) {
        }
        $zip->close();
        array_map(fn (string $f) => @unlink($f), $cleanup);

        return response()->download($zipPath, $this->fileName($meeting, 'Meeting package').'.zip')->deleteFileAfterSend();
    }

    private function fileName(Meeting $meeting, string $what): string
    {
        return trim(preg_replace('/[^A-Za-z0-9 ._()-]+/', '-', "{$meeting->club->name} - {$meeting->title} - {$what} - {$meeting->starts_at->format('Y-m-d')}"), ' -');
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
