<?php

namespace App\Services\Meetings;

use App\Enums\MembershipStatus;
use App\Enums\ScopedRole;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\Membership;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Time\SessionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who sees which meetings outside the meeting page itself (feed, calendar, club profile):
 * - a club's committee and advisors: every meeting of that club;
 * - its members and followers: weekly activities open to all members;
 * - SLD: every meeting (calendar and reports);
 * - everyone else: nothing in the feed, but a club's all-member activities on its public profile.
 * Committee meetings never show to members or followers.
 */
class MeetingVisibility
{
    public function __construct(private readonly SessionResolver $sessions) {}

    /** @return Collection<int, int> clubs the user runs (committee or advisor) this session */
    public function runs(User $user): Collection
    {
        return RoleAssignment::query()->acting($this->sessions->current(), training: true)
            ->where('user_id', $user->id)->whereIn('role', [ScopedRole::ClubCommittee, ScopedRole::Advisor])
            ->pluck('scope_id')->unique()->values();
    }

    /** @return Collection<int, int> clubs the user belongs to (active or grace) or follows */
    public function connected(User $user): Collection
    {
        return Membership::where('user_id', $user->id)->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace])->pluck('club_id')
            ->merge($user->followedClubs()->pluck('clubs.id'))->unique()->values();
    }

    /** Meetings this user may see in the feed and calendar. SLD sees every meeting when $sldSeesAll. */
    public function query(User $user, bool $sldSeesAll = false): Builder
    {
        $query = Meeting::query()->with('club');
        if ($sldSeesAll && $user->isSldAdmin()) {
            return $query;
        }
        $runs = $this->runs($user);
        $connected = $this->connected($user);

        return $query->where(fn (Builder $q) => $q->whereIn('club_id', $runs)
            ->orWhere(fn (Builder $q) => $this->openToMembers($q)->whereIn('club_id', $connected)));
    }

    /** Meetings shown on a club's profile: all-member activities for everyone; every meeting for its committee and SLD. */
    public function forProfile(Club $club, User $viewer): Builder
    {
        return $club->meetings()->getQuery()
            ->when(! $viewer->can('viewInternal', $club), fn (Builder $q) => $this->openToMembers($q));
    }

    /** @return array<string, mixed> a meeting as a card for the feed, calendar or club profile */
    public function card(Meeting $meeting, User $viewer): array
    {
        $canOpen = $viewer->can('viewInternal', $meeting->club);
        $audience = $meeting->kind === 'activity' ? $meeting->audience : 'committee';

        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'kind' => $meeting->kind,
            'kind_label' => Meeting::KINDS[$meeting->kind] ?? $meeting->kind,
            'audience' => Meeting::AUDIENCES[$audience] ?? $audience,
            'starts_at' => $meeting->starts_at->toIso8601String(),
            'ends_at' => $meeting->ends_at->toIso8601String(),
            'venue' => $meeting->venue,
            'online' => $meeting->meet_link !== null,
            // The Meet link goes only to the people it is for.
            'meet_link' => $canOpen || $this->isInvited($meeting, $viewer) ? $meeting->meet_link : null,
            'club' => ['slug' => $meeting->club->slug, 'name' => $meeting->club->name, 'logo_url' => $meeting->club->logoUrl()],
            'url' => $canOpen ? route('meetings.show', $meeting) : route('clubs.show', $meeting->club),
            'can_open' => $canOpen,
        ];
    }

    private function isInvited(Meeting $meeting, User $viewer): bool
    {
        return $meeting->kind === 'activity' && $meeting->audience === 'members'
            && Membership::where('user_id', $viewer->id)->where('club_id', $meeting->club_id)->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace])->exists();
    }

    private function openToMembers(Builder $query): Builder
    {
        return $query->where('kind', 'activity')->where('audience', 'members');
    }
}
