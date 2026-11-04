<?php

namespace App\Http\Controllers\Events;

use App\Enums\ScopedRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\IndemnityRequest;
use App\Models\Event;
use App\Models\User;
use App\Services\Documents\IndemnityPdf;
use App\Services\Events\IndemnityService;
use App\Support\DownloadName;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class IndemnityController extends Controller
{
    public function __construct(private readonly IndemnityService $indemnities) {}

    /** Student form, auto-filled and read-only where the system knows the answer. */
    public function create(Request $request, Event $event): Response
    {
        Gate::authorize('view', $event);
        $user = $request->user();
        $student = $user->student;
        $advisor = $event->club->roleAssignments()->where('session_id', $event->session_id)->where('role', ScopedRole::Advisor)->first()?->user;

        return Inertia::render('Events/Indemnity', [
            'event' => [
                ...$event->only('slug', 'name', 'venue'),
                'dates' => $event->starts_at->format('j M Y').($event->ends_at->isSameDay($event->starts_at) ? '' : ' – '.$event->ends_at->format('j M Y')),
                'organizer' => $event->club->name,
                'staff_in_charge' => $advisor?->name,
                'closes_at' => $event->indemnityClosesAt()->format('D, j M Y, g:i A'),
                'closed' => now()->gte($event->indemnityClosesAt()),
            ],
            'student' => [
                'name' => $user->name,
                'student_number' => $student?->student_number,
                'programme' => $student?->programme,
                'contact_number' => $student?->mobile_no,
            ],
            'saved' => $this->indemnities->savedDetails($user),
            'signedAt' => $event->indemnities()->where('user_id', $user->id)->first()?->signed_at?->format('D, j M Y, g:i A'),
            'mustSign' => $this->indemnities->mustSign($event)->contains('id', $user->id),
        ]);
    }

    public function store(IndemnityRequest $request, Event $event): RedirectResponse
    {
        Gate::authorize('view', $event);
        try {
            $this->indemnities->sign($event, $request->user(), $request->fields(), $request->validated('signature'), $request->validated('signature_strokes'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $registration = $event->registrations()->where('user_id', $request->user()->id)->first();

        return $registration?->isPaidUp() && $registration->ensureTicket()->ticket_code
            ? to_route('tickets.show', $registration->ticket_code)->with('success', 'Indemnity form signed. Your ticket is ready.')
            : to_route('events.show', $event)->with('success', 'Indemnity signed.');
    }

    /** Signed / not-signed list for the event committee, club committee, advisor and SLD. */
    public function index(Event $event): Response
    {
        Gate::authorize('organises', $event);
        $signed = $event->indemnities()->pluck('signed_at', 'user_id');

        return Inertia::render('Events/Indemnities', [
            'event' => $event->only('slug', 'name'),
            'people' => $this->indemnities->mustSign($event)->map(fn (User $u) => [
                'name' => $u->name,
                'student_number' => $u->student?->student_number,
                'signed_at' => isset($signed[$u->id]) ? (string) $signed[$u->id] : null,
            ]),
        ]);
    }

    public function download(Event $event, IndemnityPdf $pdf): BinaryFileResponse
    {
        Gate::authorize('organises', $event);

        return response()->download($pdf->render($event), DownloadName::safe("Indemnity - {$event->name}.pdf"))->deleteFileAfterSend();
    }
}
