<?php

namespace App\Services\Agm;

use App\Enums\DocumentType;
use App\Enums\MembershipStatus;
use App\Models\Agm;
use App\Models\AgmPeriod;
use App\Models\Club;
use App\Models\Document;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use App\Services\Google\CalendarMeetService;
use App\Services\Meetings\Meetings;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/** Scheduling an AGM (date, time, venue, Meet link) and taking its attendance by QR or by hand. */
class Agms
{
    public function __construct(
        private readonly AgmPeriods $periods,
        private readonly CalendarMeetService $calendar,
        private readonly Meetings $meetings,
        private readonly AuditLogger $audit,
    ) {}

    public function for(Club $club, ?AgmPeriod $period): ?Agm
    {
        return $period ? Agm::where('club_id', $club->id)->where('agm_period_id', $period->id)->first() : null;
    }

    /** @param array{starts_at: string, ends_at: string, venue: ?string, online: bool} $data */
    public function schedule(Club $club, User $organiser, array $data): Agm
    {
        $period = $this->periods->assertOpen($this->periods->current());
        if ($this->for($club, $period)) {
            throw new DomainException("{$club->name} already has an AGM in this period.");
        }
        $start = CarbonImmutable::parse($data['starts_at']);
        $end = CarbonImmutable::parse($data['ends_at']);
        if ($start->startOfDay()->gt($period->agm_deadline) || $start->lt($period->starts_on)) {
            throw new DomainException('Hold the AGM between '.$period->starts_on->format('j M').' and '.$period->agm_deadline->format('j M Y').'.');
        }

        // Every member is invited to the AGM, not just the committee.
        $invitees = $this->meetings->committee($club)
            ->merge($club->memberships()->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace])->with('user')->get()->pluck('user'))
            ->unique('id');
        $meet = $data['online']
            ? $this->calendar->createMeeting($organiser, "{$club->name}: Annual General Meeting", $start, $end, $invitees->pluck('email')->all())
            : null;

        return DB::transaction(function () use ($club, $organiser, $period, $start, $end, $data, $meet, $invitees): Agm {
            $agm = Agm::create([
                'club_id' => $club->id, 'agm_period_id' => $period->id, 'session_id' => $period->session_id,
                'starts_at' => $start, 'ends_at' => $end, 'venue' => $data['venue'] ?? null,
                'meet_link' => $meet['link'] ?? null, 'google_event_id' => $meet['event_id'] ?? null,
                'attendance_secret' => Str::random(40), 'organiser_id' => $organiser->id,
            ]);
            Document::create([
                'type' => DocumentType::AgmReport, 'club_id' => $club->id, 'agm_id' => $agm->id,
                'session_id' => $period->session_id, 'created_by' => $organiser->id,
            ]);
            $club->forceFill(['last_activity_at' => now()])->save();
            $this->audit->log('agm.scheduled', $agm, ['starts_at' => $start->toDateTimeString()]);
            Notification::send($invitees->reject(fn (User $u) => $u->is($organiser)), new DocumentNotification(
                "{$club->name}: Annual General Meeting",
                $start->format('D j M, g:i A').($meet ? ' · Google Meet' : ($data['venue'] ? " · {$data['venue']}" : '')).'. Scan the QR code at the AGM to mark attendance.',
                route('agm.show', $club),
            ));

            return $agm;
        });
    }

    /** Members (not alumni) and the club's committee and advisor can be marked present, from 1 hour before until it ends. */
    public function attend(Agm $agm, User $user, string $method): void
    {
        if (now()->lt($agm->starts_at->subHour()) || now()->gt($agm->ends_at->addHour())) {
            throw new DomainException('Attendance opens 1 hour before the AGM and closes 1 hour after it ends.');
        }
        $isMember = $agm->club->memberships()->where('user_id', $user->id)
            ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace])->exists();
        if (! $isMember && ! $this->meetings->committee($agm->club)->contains('id', $user->id)) {
            throw new DomainException("{$user->name} is not a member of {$agm->club->name}.");
        }
        if (! $agm->attendees()->whereKey($user->id)->exists()) {
            $agm->attendees()->attach($user->id, ['method' => $method, 'attended_at' => now()]);
        }
    }
}
