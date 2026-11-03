<?php

namespace App\Services\Meetings;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ScopedRole;
use App\Events\DocumentApproved;
use App\Models\CcaRecord;
use App\Models\Club;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\DocumentNotification;
use App\Services\Google\CalendarMeetService;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/** Committee meetings and weekly activities: Meet link, invitations, attendance, minutes, weekly CCA. */
class Meetings
{
    public function __construct(
        private readonly CalendarMeetService $calendar,
        private readonly SessionResolver $sessions,
    ) {}

    /**
     * A committee meeting is for the committee and advisors only. A weekly activity is for whoever the organiser
     * picks (the committee, or every member) and states its expected pax and location. Everyone invited is notified.
     *
     * @param  array{kind: string, title: string, starts_at: string, ends_at: string, venue: ?string, online: bool, audience?: ?string, pax?: ?int}  $data
     */
    public function create(Club $club, User $organiser, array $data): Meeting
    {
        $session = $this->sessions->current();
        $start = CarbonImmutable::parse($data['starts_at']);
        $end = CarbonImmutable::parse($data['ends_at']);
        $activity = $data['kind'] === 'activity';
        $audience = $activity && ($data['audience'] ?? null) === 'members' ? 'members' : 'committee';
        $invitees = $this->audience($club, $audience);

        $meet = $data['online']
            ? $this->calendar->createMeeting($organiser, "{$club->name}: {$data['title']}", $start, $end, $invitees->pluck('email')->all())
            : null;

        return DB::transaction(function () use ($club, $organiser, $data, $session, $start, $end, $meet, $invitees, $activity, $audience): Meeting {
            $meeting = $club->meetings()->create([
                'session_id' => $session->id, 'kind' => $data['kind'], 'audience' => $audience, 'pax' => $activity ? ($data['pax'] ?? null) : null, 'title' => $data['title'],
                'starts_at' => $start, 'ends_at' => $end, 'venue' => $data['venue'] ?? null,
                'meet_link' => $meet['link'] ?? null, 'google_event_id' => $meet['event_id'] ?? null, 'organiser_id' => $organiser->id,
            ]);
            Document::create([
                'type' => DocumentType::Minutes, 'club_id' => $club->id, 'meeting_id' => $meeting->id,
                'session_id' => $session->id, 'created_by' => $organiser->id,
            ]);
            $club->forceFill(['last_activity_at' => now()])->save();
            Notification::send($invitees->reject(fn (User $u) => $u->is($organiser)), new DocumentNotification(
                ($activity ? 'Weekly activity' : 'Committee meeting')." · {$club->name}: {$data['title']}",
                $start->format('D j M, g:i A').($data['venue'] ? " · {$data['venue']}" : '').($meet ? ' · Google Meet' : '').($activity && ! empty($data['pax']) ? " · {$data['pax']} pax" : ''),
                route('meetings.show', $meeting),
            ));

            return $meeting;
        });
    }

    /**
     * The committee ticks people present by hand. Someone who scanned in keeps their scan times; anyone newly
     * ticked is recorded as 'manual'.
     *
     * @param  list<int>  $presentUserIds
     */
    public function recordAttendance(Meeting $meeting, array $presentUserIds): void
    {
        $scanned = $meeting->attendees()->wherePivotNotNull('checked_in_at')->pluck('users.id')->all();
        $everyone = $this->invitees($meeting)->pluck('id');
        $meeting->attendees()->sync($everyone->mapWithKeys(function (int $id) use ($presentUserIds, $scanned): array {
            $present = in_array($id, $presentUserIds, true);

            return [$id => ['present' => $present, ...(in_array($id, $scanned, true) ? [] : ['method' => $present ? 'manual' : null])]];
        })->all());
    }

    /**
     * Someone scanned the meeting's check-in or check-out QR with their own phone. Only the meeting's audience may:
     * the committee and advisors for a committee meeting (or a committee-only activity), every member for an
     * all-members activity. Check-in opens 30 minutes early; check-out stays open 2 hours after the end.
     */
    public function clock(Meeting $meeting, User $user, string $action): string
    {
        if (! $this->invitees($meeting)->contains('id', $user->id)) {
            throw new DomainException($meeting->kind === 'activity' && $meeting->audience === 'members'
                ? "This activity is for {$meeting->club->name} members only."
                : "This meeting is for the {$meeting->club->name} committee and advisors only.");
        }
        $now = now();
        $row = $meeting->attendees()->whereKey($user->id)->first()?->pivot;
        if ($action === 'in') {
            if ($now->lt($meeting->starts_at->copy()->subMinutes(Meeting::CHECK_IN_EARLY_MINUTES))) {
                throw new DomainException('Check-in opens 30 minutes before the meeting starts.');
            }
            if ($now->gt($meeting->ends_at)) {
                throw new DomainException('This meeting has ended, so check-in is closed.');
            }
            if ($row?->checked_in_at) {
                throw new DomainException('You already checked in at '.CarbonImmutable::parse($row->checked_in_at)->format('g:i A').'.');
            }
            $meeting->attendees()->syncWithoutDetaching([$user->id => ['present' => true, 'checked_in_at' => $now, 'method' => 'qr']]);

            return "Checked in to {$meeting->title}.";
        }
        if ($now->lt($meeting->starts_at)) {
            throw new DomainException('Check-out opens when the meeting starts.');
        }
        if ($now->gt($meeting->ends_at->copy()->addMinutes(Meeting::CHECK_OUT_LATE_MINUTES))) {
            throw new DomainException('Check-out for this meeting has closed.');
        }
        if (! $row?->checked_in_at) {
            throw new DomainException('Check in first — scan the check-in code.');
        }
        if ($row->checked_out_at) {
            throw new DomainException('You already checked out at '.CarbonImmutable::parse($row->checked_out_at)->format('g:i A').'.');
        }
        $meeting->attendees()->updateExistingPivot($user->id, ['checked_out_at' => $now]);

        return "Checked out of {$meeting->title}. Thanks for coming!";
    }

    /** Weekly activity CCA: once its minutes are approved, every present attendee earns the activity's hours. */
    public function handleApproved(DocumentApproved $approved): void
    {
        $document = $approved->document;
        if ($document->type !== DocumentType::Minutes || ! $meeting = $document->meeting) {
            return;
        }
        if ($meeting->kind !== 'activity') {
            return;
        }
        $hours = round($meeting->starts_at->diffInMinutes($meeting->ends_at, absolute: true) / 60, 1);
        foreach ($meeting->attendees()->wherePivot('present', true)->get() as $user) {
            CcaRecord::updateOrCreate(['user_id' => $user->id, 'meeting_id' => $meeting->id], [
                'session_id' => $meeting->session_id, 'role' => 'participant', 'hours' => $hours,
            ]);
        }
    }

    public function assertCanSubmit(Meeting $meeting): void
    {
        if ($meeting->kind === 'activity' && ! $meeting->attendees()->wherePivot('present', true)->exists()) {
            throw new DomainException('Take attendance first — a weekly CCA claim needs both minutes and attendance.');
        }
        if ($meeting->minutes?->status === DocumentStatus::Approved) {
            throw new DomainException('These minutes are already approved.');
        }
    }

    /** @return Collection<int, User> who the meeting is for (and whose attendance is taken) */
    public function invitees(Meeting $meeting): Collection
    {
        return $this->audience($meeting->club, $meeting->kind === 'activity' ? $meeting->audience : 'committee');
    }

    /** @return Collection<int, User> committee + advisors, plus every active member when the audience is 'members' */
    public function audience(Club $club, string $audience): Collection
    {
        $committee = $this->committee($club);
        if ($audience !== 'members') {
            return $committee;
        }

        return $committee->merge(User::whereIn('id', $club->memberships()->whereIn('status', ['active', 'grace'])->select('user_id'))->get())->unique('id')->values();
    }

    /** @return Collection<int, User> */
    public function committee(Club $club)
    {
        return User::whereIn('id', RoleAssignment::query()->actingFor($club->id, $this->sessions->current())
            ->whereIn('role', [ScopedRole::ClubCommittee, ScopedRole::Advisor])->select('user_id'))->get();
    }
}
