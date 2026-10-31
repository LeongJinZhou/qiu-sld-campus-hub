<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\AuditLogger;
use App\Services\Events\EventRegistrations;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

    /** Committee or SLD choose who may register: anyone at QIU, or club members only. */
    public function settings(Request $request, Event $event, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $event);
        $data = $request->validate(['members_only' => ['required', 'boolean']]);
        $event->update($data);
        $audit->log('event.registration_settings', $event, $data);

        return back()->with('success', $event->members_only ? "Only {$event->club->name} members can register now." : 'Anyone at QIU can register now.');
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $event->registrations()->where('user_id', $request->user()->id)->delete();

        return back()->with('success', 'Registration cancelled.');
    }
}
