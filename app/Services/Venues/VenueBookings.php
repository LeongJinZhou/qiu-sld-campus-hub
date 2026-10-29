<?php

namespace App\Services\Venues;

use App\Models\Club;
use App\Models\User;
use App\Models\VenueBooking;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Notification;

/**
 * Venue requests (CLAUDE.md §6 Venue): La Place booth (form + email to SLD, SLD confirms in the app),
 * semester permanent venues, and one-off big rooms booked at least a week ahead.
 */
class VenueBookings
{
    public const BIG_ROOM_LEAD_DAYS = 7;

    /** From the SLD "SOP to book place @ La Place". */
    public const LA_PLACE_RULES = [
        'Use only the designated table provided.',
        'Set up as shown in the demonstration image, leaving a walkway for students to pass through.',
        'Return the table to its original position after use.',
        'Clean up and leave the area tidy after use.',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data validated input */
    public function request(Club $club, User $user, array $data): VenueBooking
    {
        $type = $data['type'];
        if ($type === 'big_room' && CarbonImmutable::parse($data['date'])->lt(CarbonImmutable::today()->addDays(self::BIG_ROOM_LEAD_DAYS))) {
            throw new DomainException('Big rooms must be booked at least 1 week ahead.');
        }
        if ($type !== 'semester' && CarbonImmutable::parse($data['date'])->isPast() && ! CarbonImmutable::parse($data['date'])->isToday()) {
            throw new DomainException('Choose a date from today onwards.');
        }

        $booking = VenueBooking::create([...$data, 'club_id' => $club->id, 'user_id' => $user->id, 'status' => 'pending',
            'venue' => $type === 'la_place' ? 'La Place' : $data['venue']]);
        $this->audit->log('venue.requested', $booking, ['type' => $type]);

        // The SOP's email to SLD, plus an in-app notice; SLD confirms in the app.
        Notification::send(User::role(['admin', 'superadmin'])->get(), new DocumentNotification(
            "Venue request: {$club->name} · ".VenueBooking::TYPES[$type],
            $this->summary($booking),
            route('admin.venues.index'),
        ));

        return $booking;
    }

    public function decide(VenueBooking $booking, User $sld, bool $confirm, ?string $note): void
    {
        if ($booking->status !== 'pending') {
            throw new DomainException('This request has already been answered.');
        }
        $booking->update(['status' => $confirm ? 'confirmed' : 'declined', 'sld_note' => $note, 'decided_by' => $sld->id, 'decided_at' => now()]);
        $this->audit->log($confirm ? 'venue.confirmed' : 'venue.declined', $booking, ['note' => $note]);
        $booking->club->forceFill(['last_activity_at' => now()])->save();

        $booking->user->notify(new DocumentNotification(
            $confirm ? 'Venue booking confirmed' : 'Venue booking declined',
            $this->summary($booking).($note ? " — SLD: {$note}" : '').($confirm && $booking->type === 'la_place' ? ' Please follow the La Place table rules.' : ''),
            route('venues.index', $booking->club),
        ));
    }

    public function summary(VenueBooking $b): string
    {
        $when = $b->type === 'semester' ? "every {$b->weekday} ({$b->semester})" : $b->date?->format('D, j M Y');

        return sprintf('%s at %s, %s %s–%s. Purpose: %s.%s', VenueBooking::TYPES[$b->type], $b->venue, $when, substr((string) $b->starts_at, 0, 5), substr((string) $b->ends_at, 0, 5), $b->purpose, $b->items ? " Items: {$b->items}." : '');
    }
}
