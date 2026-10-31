<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Services\Events\Tickets;
use App\Services\QrCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The attendee's ticket, and what happens when someone scans its QR. */
class TicketController extends Controller
{
    public function show(Request $request, string $code, Tickets $tickets, QrCodes $qr): Response
    {
        $registration = EventRegistration::with('event')->where('ticket_code', $code)->firstOrFail();
        abort_unless($registration->user_id === $request->user()->id || $request->user()->can('checkIn', $registration->event), 403);

        return Inertia::render('Tickets/Show', [
            'ticket' => $tickets->present($registration),
            'qrSvg' => $qr->svg(route('tickets.scan', $code)),
            'mine' => $registration->user_id === $request->user()->id,
        ]);
    }

    /** Scanned with any phone camera: the door desk for organisers and helpers, the ticket for its owner. */
    public function scan(Request $request, string $code): RedirectResponse
    {
        $registration = EventRegistration::with('event')->where('ticket_code', $code)->firstOrFail();
        if ($request->user()->can('checkIn', $registration->event)) {
            return to_route('events.attendance', ['event' => $registration->event, 'find' => $code]);
        }
        abort_unless($registration->user_id === $request->user()->id, 403, 'Only the event committee and helpers can check tickets.');

        return to_route('tickets.show', $code);
    }
}
