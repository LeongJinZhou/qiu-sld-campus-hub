<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\Student;
use App\Models\User;
use App\Services\Documents\AttendanceListExport;
use App\Services\Events\AttendanceQr;
use App\Services\Events\AttendanceService;
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

    /** Committee screen: rotating clock-in/out QR, scanner for personal QRs, manual add, list. */
    public function index(Request $request, Event $event, QrCodes $codes): Response
    {
        Gate::authorize('organises', $event);
        $action = $request->query('action') === 'out' ? 'out' : 'in';

        return Inertia::render('Events/Attendance', [
            'event' => [...$event->only('slug', 'name', 'is_outdoor', 'attendance_open'), 'starts_at' => $event->starts_at->toIso8601String()],
            'action' => $action,
            'qrSvg' => $event->attendance_open ? $codes->svg($this->qr->url($event, $action)) : null,
            'rows' => $event->attendance()->with('user.student')->latest('updated_at')->get()->map(fn (Attendance $a) => [
                'id' => $a->id,
                'name' => $a->user->name,
                'student_number' => $a->user->student?->student_number,
                'role' => $a->role,
                'in' => $a->clock_in_at?->format('g:i A'),
                'out' => $a->clock_out_at?->format('g:i A'),
                'method' => $a->method,
            ]),
            'counts' => ['registered' => $event->registrations()->count(), 'present' => $event->attendance()->whereNotNull('clock_in_at')->count()],
        ]);
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

    /** Committee scanned a student's personal profile QR, or typed a student ID. */
    public function record(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('organises', $event);
        $data = $request->validate([
            'action' => ['required', Rule::in(['in', 'out', 'manual'])],
            'code' => ['nullable', 'required_without:student_number', 'string', 'max:500'],
            'student_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user = isset($data['student_number'])
            ? Student::where('student_number', strtoupper(trim($data['student_number'])))->first()?->ensureUser()
            : User::where('qr_token', $this->profileToken((string) $data['code']))->first();
        if (! $user) {
            return back()->with('error', 'No student found for that code.');
        }

        try {
            $data['action'] === 'manual'
                ? $this->attendance->addManually($event, $user, $request->user())
                : $this->attendance->clock($event, $user, $data['action'], 'scan', $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$user->name}: ".($data['action'] === 'out' ? 'clocked out' : 'present').'.');
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
