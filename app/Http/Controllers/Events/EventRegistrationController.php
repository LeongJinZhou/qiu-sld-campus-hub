<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventRegistrationController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        if (! $event->registration_open || $event->ends_at->isPast()) {
            return back()->with('error', 'Registration is closed for this event.');
        }
        if (! $request->user()->student?->isActive()) {
            return back()->with('error', 'Only active QIU students can register.');
        }
        $event->registrations()->firstOrCreate(['user_id' => $request->user()->id]);

        return back()->with('success', $event->is_outdoor ? 'Registered. Please sign the indemnity form before the event.' : 'Registered. See you there!');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $event->registrations()->where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Registration cancelled.');
    }
}
