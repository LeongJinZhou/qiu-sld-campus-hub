<?php

namespace App\Http\Controllers\Events;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Student;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Approvals\ApprovalEngine;
use App\Services\AuditLogger;
use App\Services\Documents\AttendanceListExport;
use App\Services\Documents\DocumentPresenter;
use App\Services\Events\AttendanceQr;
use App\Services\Events\AttendanceService;
use App\Services\Events\EventRegistrations;
use App\Services\Events\Tickets;
use App\Services\QrCodes;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance, private readonly AttendanceQr $qr) {}

    /**
     * The door desk. Committee, SLD and student helpers scan tickets or personal QRs (or type a name, order ID or
     * student ID) to see who has paid and clock people in and out; the screen QR rotates at the event's own pace.
     * Only organisers see the sign-off, the export, the QR timing and the helpers list.
     */
    public function index(Request $request, Event $event, QrCodes $codes, DocumentPresenter $presenter, Tickets $tickets): Response
    {
        Gate::authorize('checkIn', $event);
        $action = $request->query('action') === 'out' ? 'out' : 'in';
        $manage = $request->user()->can('organises', $event);
        $find = trim((string) $request->query('find', ''));

        $registrations = $event->registrations()->with(['user.student', 'payments'])->get()->keyBy('user_id');
        // Places confirmed before tickets existed get theirs now.
        $registrations->each(fn (EventRegistration $r) => $r->ensureTicket());
        $attendance = $event->attendance()->with('user.student')->get()->keyBy('user_id');
        $people = $registrations->map(fn (EventRegistration $r) => $this->person($r->user, $r, $attendance->get($r->user_id), $tickets))
            ->merge($attendance->except($registrations->keys()->all())->map(fn (Attendance $a) => $this->person($a->user, null, $a, $tickets)))
            ->sortBy('name')->values();

        return Inertia::render('Events/Attendance', [
            'event' => [
                ...$event->only('slug', 'name', 'is_outdoor', 'attendance_open', 'fee_sen'),
                'starts_at' => $event->starts_at->toIso8601String(),
                'club_slug' => $event->club->slug,
            ],
            'action' => $action,
            'qr' => $event->attendance_open ? [
                'svg' => $codes->svg($this->qr->url($event, $action)),
                'seconds' => $this->qr->seconds($event),
                'seconds_left' => $this->qr->secondsLeft($event),
            ] : null,
            'find' => $find,
            'results' => $find === '' ? [] : $tickets->find($event, $find)
                ->map(fn (array $hit) => $this->person($hit['user'], $hit['registration'], $attendance->get($hit['user']->id), $tickets))->values(),
            'people' => $people,
            'counts' => [
                'registered' => $registrations->count(),
                'paid' => $registrations->filter(fn (EventRegistration $r) => $r->isPaidUp())->count(),
                'unpaid' => $people->whereIn('status', ['unpaid', 'checking'])->count(),
                'in' => $attendance->whereNotNull('clock_in_at')->count(),
                'out' => $attendance->whereNotNull('clock_out_at')->count(),
            ],
            'helpers' => $manage ? $event->helpers()->with('student')->orderBy('name')->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'student_number' => $u->student?->student_number, 'photo_url' => $u->photoUrl()]) : [],
            'signOff' => $manage && ($document = Document::where('event_id', $event->id)->where('type', DocumentType::AttendanceList)->first())
                ? ['id' => $document->id, 'status' => $document->status->value, 'progress' => $presenter->progress($document), 'steps' => $presenter->present($document)['steps']]
                : null,
            'can' => ['manage' => $manage],
            'limits' => ['min' => AttendanceQr::MIN_SECONDS, 'max' => AttendanceQr::MAX_SECONDS],
        ]);
    }

    /** How often the screen QR changes. */
    public function settings(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('organises', $event);
        $data = $request->validate(['qr_rotate_seconds' => ['required', 'integer', 'between:'.AttendanceQr::MIN_SECONDS.','.AttendanceQr::MAX_SECONDS]]);
        $event->update($data);

        return back()->with('success', "The QR now changes every {$data['qr_rotate_seconds']} seconds.");
    }

    /** Organisers add a student helper by student ID or by scanning their personal QR. */
    public function addHelper(Request $request, Event $event, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('organises', $event);
        $data = $request->validate(['student_number' => ['nullable', 'required_without:code', 'string', 'max:50'], 'code' => ['nullable', 'string', 'max:500']]);
        $user = isset($data['student_number'])
            ? Student::where('student_number', strtoupper(trim($data['student_number'])))->first()?->ensureUser()
            : User::where('qr_token', $this->profileToken((string) $data['code']))->first();
        if (! $user?->student?->isActive()) {
            return back()->with('error', 'Helpers must be current QIU students.');
        }
        $event->helpers()->syncWithoutDetaching([$user->id => ['added_by' => $request->user()->id]]);
        $audit->log('event.helper_added', $event, ['user_id' => $user->id]);
        $user->notify(new DocumentNotification("You're helping at {$event->name}", 'You can scan tickets and attendance at the door.', route('events.attendance', $event)));

        return back()->with('success', "{$user->name} can now scan tickets and attendance.");
    }

    public function removeHelper(Request $request, Event $event, User $helper, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('organises', $event);
        $event->helpers()->detach($helper->id);
        $audit->log('event.helper_removed', $event, ['user_id' => $helper->id]);

        return back()->with('success', "{$helper->name} is no longer a helper.");
    }

    /** @return array<string, mixed> */
    private function person(User $user, ?EventRegistration $registration, ?Attendance $attendance, Tickets $tickets): array
    {
        $status = $registration ? $tickets->status($registration) : ($attendance?->role === 'committee' ? 'committee' : 'walk_in');
        if ($registration && $attendance?->role === 'committee') {
            $status = 'committee';
        }

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'photo_url' => $user->photoUrl(),
            'student_number' => $user->student?->student_number,
            'registered' => $registration !== null,
            'status' => $status,
            'order_id' => $registration?->order_id ?? $registration?->payments->first(fn ($p) => $p->status->isPaid())?->order_id,
            'in' => $attendance?->clock_in_at?->format('g:i A'),
            'out' => $attendance?->clock_out_at?->format('g:i A'),
            'method' => $attendance?->method,
            'key' => $user->student?->student_number ?? $user->qr_token,
        ];
    }

    /** Preparer signs the 03 Attendance List; it then goes to the Club Advisor and SLD. */
    public function submit(Request $request, Event $event, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('organises', $event);
        $data = $request->validate(['signature' => ['required', 'string'], 'signature_strokes' => ['required', 'string', 'max:307200']]);
        if (! $event->attendance_open) {
            return back()->with('error', 'Attendance opens once the proposal is approved.');
        }

        $document = Document::firstOrCreate(
            ['event_id' => $event->id, 'type' => DocumentType::AttendanceList],
            ['club_id' => $event->club_id, 'session_id' => $event->session_id, 'created_by' => $request->user()->id],
        );
        $present = $event->attendance()->whereNotNull('clock_in_at');

        try {
            $engine->submit($document, $request->user(), [
                'participants' => (clone $present)->where('role', 'participant')->count(),
                'committee' => (clone $present)->where('role', 'committee')->count(),
                'registered' => $event->registrations()->count(),
            ], $data['signature'], $data['signature_strokes']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Attendance list signed and sent to your Club Advisor.');
    }

    /** A student scanned the event's rotating QR with their own phone. */
    public function attend(Request $request, Event $event): RedirectResponse
    {
        $action = (string) $request->query('action');
        if (! $this->qr->isValid($event, $action, (string) $request->query('code'))) {
            return to_route('events.show', $event)->with('error', 'This QR code has expired. Scan the code on screen again.');
        }

        try {
            $this->attendance->clock($event, $request->user(), $action, 'qr');
        } catch (DomainException $e) {
            return to_route('events.show', $event)->with('error', $e->getMessage());
        }

        return to_route('events.show', $event)->with('success', $action === 'in' ? 'Clocked in. Enjoy!' : 'Clocked out. CCA recorded.');
    }

    /** Committee scanned a student's personal profile QR (to register, clock in or out), or typed a student ID. */
    public function record(Request $request, Event $event, EventRegistrations $registrations, Tickets $tickets): RedirectResponse
    {
        Gate::authorize('checkIn', $event);
        $data = $request->validate([
            'action' => ['required', Rule::in(['register', 'in', 'out', 'manual', 'check'])],
            'code' => ['nullable', 'required_without:student_number', 'string', 'max:500'],
            'student_number' => ['nullable', 'string', 'max:50'],
        ]);

        // A ticket QR, a personal QR, an order ID or a student ID all point at one person.
        $input = (string) ($data['code'] ?? $data['student_number']);
        $hits = $tickets->find($event, $input);
        $user = $hits->count() === 1 ? $hits->first()['user'] : null;
        $desk = fn (string $find) => to_route('events.attendance', ['event' => $event, 'find' => $find, 'action' => $request->query('action', 'in')]);
        if (! $user) {
            return $desk($input)->with('error', $hits->isEmpty() ? 'No ticket or student found for that code.' : 'More than one person matches — pick one below.');
        }
        $find = $user->student?->student_number ?? $input;
        if ($data['action'] === 'check') {
            return $desk($find);
        }

        try {
            match ($data['action']) {
                'register' => $registrations->register($event, $user),
                'manual' => $this->attendance->addManually($event, $user, $request->user()),
                default => $this->attendance->clock($event, $user, $data['action'], 'scan', $request->user()),
            };
        } catch (DomainException $e) {
            return $desk($find)->with('error', $e->getMessage());
        }

        return $desk($find)->with('success', "{$user->name}: ".match ($data['action']) {
            'register' => 'registered', 'out' => 'clocked out', default => 'present'
        }.'.');
    }

    public function export(Event $event, AttendanceListExport $export): BinaryFileResponse
    {
        Gate::authorize('organises', $event);

        return response()->download($export->render($event), "Attendance - {$event->name}.xlsx")->deleteFileAfterSend();
    }

    /** Accepts a full /p/{token} URL or the bare token. */
    private function profileToken(string $code): string
    {
        return preg_match('#/p/([A-Za-z0-9]{32})#', $code, $m) ? $m[1] : trim($code);
    }
}
