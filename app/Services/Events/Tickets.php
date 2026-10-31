<?php

namespace App\Services\Events;

use App\Enums\PaymentStatus;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\MembershipPayment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Event tickets: what the attendee sees (event, their name, order/transaction ID, paid or free, check-in times)
 * and what the door desk needs, found from a scanned ticket QR, a personal profile QR, an order ID, a student ID
 * or part of a name.
 */
class Tickets
{
    /** @return array<string, mixed> */
    public function present(EventRegistration $registration): array
    {
        $registration->ensureTicket()->loadMissing('event.club', 'user.student');
        $event = $registration->event;
        $paid = $this->paidPayment($registration);
        $attendance = Attendance::where('event_id', $event->id)->where('user_id', $registration->user_id)->first();

        return [
            'code' => $registration->ticket_code,
            'url' => $registration->ticket_code ? route('tickets.show', $registration->ticket_code) : null,
            'status' => $this->status($registration),
            'order_id' => $registration->order_id ?? $paid?->order_id,
            'amount_sen' => $paid?->amount_sen ?? 0,
            'method' => $paid?->method,
            'attendee' => [
                'name' => $registration->user->name,
                'student_number' => $registration->user->student?->student_number,
                'photo_url' => $registration->user->photoUrl(),
            ],
            'event' => [
                'slug' => $event->slug, 'name' => $event->name, 'club' => $event->club->name, 'club_logo' => $event->club->logoUrl(),
                'starts_at' => $event->starts_at->toIso8601String(), 'ends_at' => $event->ends_at->toIso8601String(),
                'venue' => $event->venue, 'poster_url' => $event->posterUrl(), 'past' => $event->ends_at->isPast(),
                'needs_indemnity' => $event->is_outdoor && ! $event->indemnities()->where('user_id', $registration->user_id)->exists(),
            ],
            'theme' => $this->theme($event),
            'clock_in' => $attendance?->clock_in_at?->format('g:i A'),
            'clock_out' => $attendance?->clock_out_at?->format('g:i A'),
        ];
    }

    /** The ticket's look follows the event: outdoors, online, or a party for everything else. */
    public function theme(Event $event): string
    {
        return match (true) {
            $event->is_outdoor => 'nature',
            $event->mode === 'online' => 'online',
            default => 'party',
        };
    }

    /** Paid · Free · Payment pending · Being checked */
    public function status(EventRegistration $registration): string
    {
        if ($registration->isPaidUp()) {
            return $this->paidPayment($registration) ? 'paid' : 'free';
        }

        return $registration->payments->contains(fn (MembershipPayment $p) => in_array($p->status, [PaymentStatus::Reading, PaymentStatus::PendingReview, PaymentStatus::CashPending], true))
            ? 'checking' : 'unpaid';
    }

    /**
     * Everyone the desk input could mean for this event: a ticket or profile QR, an order ID or a student ID give
     * one person; a name gives up to 10 registered people. Unregistered people come back without a registration.
     *
     * @return Collection<int, array{user: User, registration: ?EventRegistration}>
     */
    public function find(Event $event, string $input): Collection
    {
        $input = trim($input);
        if ($input === '') {
            return collect();
        }
        $registrations = EventRegistration::with('user.student')->where('event_id', $event->id);

        if (preg_match('#/t/([A-Za-z0-9]{32})#', $input, $m) || preg_match('/^[A-Za-z0-9]{32}$/', $input)) {
            $code = $m[1] ?? $input;
            $match = (clone $registrations)->where('ticket_code', $code)->first();
            // A personal profile QR (bare token) also lands here.
            $user = $match?->user ?? User::where('qr_token', $code)->first();

            return $user ? collect([$this->pair($event, $user, $match)]) : collect();
        }
        if (preg_match('#/p/([A-Za-z0-9]{32})#', $input, $m)) {
            $user = User::where('qr_token', $m[1])->first();

            return $user ? collect([$this->pair($event, $user)]) : collect();
        }
        $byOrder = (clone $registrations)->where(fn ($q) => $q->where('order_id', $input)
            ->orWhereIn('id', MembershipPayment::where('order_id', $input)->select('event_registration_id')))->first();
        if ($byOrder) {
            return collect([$this->pair($event, $byOrder->user, $byOrder)]);
        }
        if ($student = Student::where('student_number', strtoupper($input))->first()) {
            return collect([$this->pair($event, $student->ensureUser())]);
        }

        $like = '%'.addcslashes($input, '%_\\').'%';

        return (clone $registrations)->whereHas('user', fn ($q) => $q->where('name', 'like', $like))->limit(10)->get()
            ->map(fn (EventRegistration $r) => ['user' => $r->user, 'registration' => $r]);
    }

    /** @return array{user: User, registration: ?EventRegistration} */
    private function pair(Event $event, User $user, ?EventRegistration $registration = null): array
    {
        return ['user' => $user, 'registration' => $registration ?? EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first()];
    }

    private function paidPayment(EventRegistration $registration): ?MembershipPayment
    {
        return $registration->payments->filter(fn (MembershipPayment $p) => $p->status->isPaid())->sortByDesc('id')->first();
    }
}
