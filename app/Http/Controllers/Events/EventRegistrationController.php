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

        // Paid events go to the payment page; outdoor events need the indemnity before anyone can clock in.
        if ($event->fee_sen > 0) {
            return to_route('events.pay', $event)->with('success', 'Place reserved. Pay the fee to confirm your registration.');
        }

        return $event->is_outdoor
            ? to_route('events.indemnity', $event)->with('success', 'Registered! One more step: sign the indemnity form for this outdoor event.')
            : back()->with('success', 'Registered. See you there!');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $event->registrations()->where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Registration cancelled.');
    }
}
