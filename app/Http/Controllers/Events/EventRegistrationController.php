<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Events\EventRegistrations;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventRegistrationController extends Controller
{
    public function store(Request $request, Event $event, EventRegistrations $registrations): RedirectResponse
    {
        try {
            $registrations->register($event, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $event->is_outdoor ? 'Registered. Please sign the indemnity form before the event.' : 'Registered. See you there!');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $event->registrations()->where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Registration cancelled.');
    }
}
