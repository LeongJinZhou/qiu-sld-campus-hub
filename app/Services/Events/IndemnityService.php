<?php

namespace App\Services\Events;

use App\Enums\ScopeType;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Indemnity;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Approvals\SignatureStore;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Outdoor / off-campus events: every participant and committee member signs; signing closes 3 days before. */
class IndemnityService
{
    public function __construct(private readonly SignatureStore $signatures, private readonly AuditLogger $audit) {}

    /** @param array<string, string|null> $data validated form fields */
    public function sign(Event $event, User $user, array $data, string $signature, ?string $strokes = null): Indemnity
    {
        if (! $event->is_outdoor) {
            throw new DomainException('This event does not need an indemnity form.');
        }
        if (now()->gte($event->indemnityClosesAt())) {
            throw new DomainException('Indemnity signing closed 3 days before the event.');
        }
        if (! $this->mustSign($event)->contains('id', $user->id)) {
            throw new DomainException('Register for the event first.');
        }
        if ($event->indemnities()->where('user_id', $user->id)->exists()) {
            throw new DomainException('You have already signed.');
        }

        return DB::transaction(function () use ($event, $user, $data, $signature, $strokes): Indemnity {
            // Saved for next time so the student does not retype it.
            $user->emergencyContact()->updateOrCreate([], [
                'name' => $data['emergency_name'],
                'mobile_phone' => $data['emergency_mobile_phone'],
                'work_phone' => $data['emergency_work_phone'] ?? null,
                'relationship' => $data['emergency_relationship'],
                'alternate_name' => $data['alternate_name'] ?? null,
                'alternate_phone' => $data['alternate_phone'] ?? null,
            ]);

            $signed = $this->signatures->store($user, $signature, 'Participant', [], $strokes);
            $indemnity = $event->indemnities()->create([
                ...$data,
                'user_id' => $user->id,
                'contact_number' => $user->student?->mobile_no ?? '',
                'signature_id' => $signed->id,
                'signed_at' => $signed->signed_at,
            ]);
            $this->audit->log('indemnity.signed', $event, ['user_id' => $user->id]);

            return $indemnity;
        });
    }

    /** True while a confirmed participant of an outdoor event still has to sign before getting their ticket. */
    public function blocksTicket(EventRegistration $registration): bool
    {
        $event = $registration->event;

        return $event->is_outdoor && $registration->isPaidUp() && ! $event->indemnities()->where('user_id', $registration->user_id)->exists();
    }

    /** Once the place is confirmed (free sign-up or payment done): one step left, sign to get the ticket. */
    public function askToSign(EventRegistration $registration, ?string $lead = null): void
    {
        $registration->loadMissing('event', 'user');
        if (! $this->blocksTicket($registration)) {
            return;
        }
        $event = $registration->event;
        $registration->user->notify(new DocumentNotification(
            ($lead ? "{$lead} " : '')."One step left: sign the indemnity for {$event->name}",
            'Your details are filled in already. Check them, sign, and your ticket appears. Signing closes '.$event->indemnityClosesAt()->format('D, j M Y').'.',
            route('events.indemnity', $event),
        ));
    }

    /** What we already know, so the student only checks and signs. @return array<string, string|null>|null */
    public function savedDetails(User $user): ?array
    {
        $contact = $user->emergencyContact;
        $last = Indemnity::where('user_id', $user->id)->latest('signed_at')->first();
        if (! $contact && ! $last) {
            return null;
        }

        return [
            'emergency_name' => $contact?->name,
            'emergency_mobile_phone' => $contact?->mobile_phone,
            'emergency_work_phone' => $contact?->work_phone,
            'emergency_relationship' => $contact?->relationship,
            'alternate_name' => $contact?->alternate_name,
            'alternate_phone' => $contact?->alternate_phone,
            'address' => $last?->address,
        ];
    }

    /**
     * Registered participants plus the event committee.
     *
     * @return Collection<int, User>
     */
    public function mustSign(Event $event): Collection
    {
        $committee = RoleAssignment::where('scope_type', ScopeType::Event)->where('scope_id', $event->id)->pluck('user_id');

        return User::with('student')
            ->whereIn('id', $event->registrations()->select('user_id'))
            ->orWhereIn('id', $committee)
            ->orderBy('name')
            ->get();
    }
}
