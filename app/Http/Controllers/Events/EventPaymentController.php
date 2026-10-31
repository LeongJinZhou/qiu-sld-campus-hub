<?php

namespace App\Http\Controllers\Events;

use App\Enums\ScopedRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventPayments;
use App\Services\Finance\PaymentQrCodes;
use App\Services\Membership\CashHandovers;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Paid events: committee payment settings, and the student's pay page (online or cash). */
class EventPaymentController extends Controller
{
    public function __construct(private readonly EventPayments $payments) {}

    public function settings(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        $data = $request->validate([
            'fee_rm' => ['required', 'numeric', 'min:0', 'max:5000'],
            'payment_url' => ['nullable', 'url:https', 'max:500', 'required_unless:fee_rm,0'],
            'payment_account_name' => ['nullable', 'string', 'max:150', 'required_unless:fee_rm,0'],
            'qr' => ['nullable', 'array', 'max:2'],
            'qr.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_qr' => ['nullable', 'array'],
            'remove_qr.*' => ['string'],
        ]);
        try {
            $this->payments->configure($event, $request->user(), $data, $request->file('qr', []), array_values(array_intersect($data['remove_qr'] ?? [], $event->payment_qr_paths ?? [])));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment settings saved.');
    }

    public function show(Request $request, Event $event, PaymentQrCodes $qr): Response|RedirectResponse
    {
        $registration = $event->registrations()->where('user_id', $request->user()->id)->first();
        if (! $registration) {
            return to_route('events.show', $event)->with('error', 'Register for the event first.');
        }
        $latest = $registration->payments()->with('handover')->latest('id')->first();

        return Inertia::render('Events/Pay', [
            'event' => [...$event->only('slug', 'name', 'fee_sen', 'payment_url', 'payment_account_name', 'is_outdoor'), 'club' => $event->club->only('slug', 'name'), 'starts_at' => $event->starts_at->toIso8601String()],
            'registration' => ['status' => $registration->status, 'paid_at' => $registration->paid_at?->format('D, j M Y, g:i A')],
            'qrCodes' => $qr->for($event->payment_url, $event->payment_qr_paths),
            'payment' => $latest ? [
                'order_id' => $latest->order_id, 'method' => $latest->method, 'status' => $latest->status->value, 'flags' => $latest->flags ?? [],
                'review_note' => $latest->review_note, 'handover_id' => $latest->handover?->id,
            ] : null,
            'committee' => $event->club->roleAssignments()->where('session_id', $event->session_id)->where('role', ScopedRole::ClubCommittee)
                ->with('user')->get()->map(fn ($a) => ['id' => $a->user_id, 'name' => $a->user->name, 'position' => $a->position, 'photo_url' => $a->user->photoUrl()]),
        ]);
    }

    public function receipt(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate(['receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192']]);
        try {
            $this->payments->submitOnline($event->registrations()->where('user_id', $request->user()->id)->firstOrFail(), $data['receipt']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Receipt uploaded. Checking it now…');
    }

    public function cash(Request $request, Event $event, CashHandovers $handovers): RedirectResponse
    {
        $data = $request->validate(['committee_user_id' => ['required', 'exists:users,id']]);
        try {
            $handover = $handovers->requestForEvent($event->registrations()->where('user_id', $request->user()->id)->with('event')->firstOrFail(), User::findOrFail($data['committee_user_id']));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('handovers.show', $handover);
    }
}
