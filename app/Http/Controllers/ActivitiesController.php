<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\MembershipStatus;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Membership;
use App\Models\RoleAssignment;
use App\Services\Events\Tickets;
use App\Services\Time\SessionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Activities": my event tickets, the clubs I run (tools plus their live events, one tap to the door desk),
 * events I help at, and the clubs I've joined — nothing to search for.
 */
class ActivitiesController extends Controller
{
    public function __invoke(Request $request, SessionResolver $sessions, Tickets $tickets): Response
    {
        $user = $request->user();
        $roles = RoleAssignment::where('user_id', $user->id)->where('scope_type', ScopeType::Club)
            ->where('session_id', $sessions->current()->id)->get()->keyBy('scope_id');
        $memberships = Membership::where('user_id', $user->id)
            ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace, MembershipStatus::Pending, MembershipStatus::Alumni])->get()->keyBy('club_id');
        $clubs = Club::with('category')->whereIn('id', $roles->keys()->merge($memberships->keys())->unique())->orderBy('name')->get();

        // Approved events of the clubs I run that are coming up or finished in the last two weeks (to chase unpaid).
        $clubEvents = Event::whereIn('club_id', $roles->keys())->where('status', DocumentStatus::Approved)
            ->where('ends_at', '>', now()->subWeeks(2))->withCount('registrations')->orderBy('starts_at')->get()->groupBy('club_id');
        $registrations = EventRegistration::with(['event.club', 'user.student', 'payments'])->where('user_id', $user->id)
            ->whereHas('event', fn ($q) => $q->where('ends_at', '>', now()->subDays(30)))->get()
            // Upcoming first (soonest on top), then recent past ones.
            ->sortBy(fn (EventRegistration $r) => ($r->event->ends_at->isPast() ? '1' : '0').$r->event->starts_at->format('YmdHis'))->values();

        return Inertia::render('Activities/Index', [
            'tickets' => $registrations->map(fn (EventRegistration $r) => $tickets->present($r))->values(),
            'helping' => $user->helpingEvents()->with('club')->where('ends_at', '>', now()->subDay())->orderBy('starts_at')->get()
                ->map(fn (Event $e) => $this->eventCard($e)),
            'clubs' => $clubs->map(function (Club $club) use ($roles, $memberships, $clubEvents) {
                $role = $roles[$club->id] ?? null;
                $membership = $memberships[$club->id] ?? null;

                return [
                    'slug' => $club->slug, 'name' => $club->name, 'logo_url' => $club->logoUrl(), 'category' => $club->category?->name,
                    'role' => $role ? ($role->role === ScopedRole::Advisor ? 'Advisor' : $role->position) : null,
                    'manages' => $role !== null,
                    'events' => $role ? ($clubEvents[$club->id] ?? collect())->map(fn (Event $e) => $this->eventCard($e))->values() : [],
                    'membership' => $membership ? [
                        'status' => $membership->status->value,
                        'expires_on' => $membership->expires_on?->format('j M Y'),
                    ] : null,
                ];
            })->sortByDesc('manages')->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function eventCard(Event $event): array
    {
        $unpaid = $event->fee_sen > 0 ? $event->registrations()->where('status', 'pending_payment')->count() : 0;

        return [
            'slug' => $event->slug, 'name' => $event->name, 'club' => $event->club?->name,
            'starts_at' => $event->starts_at->toIso8601String(), 'venue' => $event->venue, 'poster_url' => $event->posterUrl(),
            'live' => $event->starts_at->copy()->subHour()->isPast() && $event->ends_at->isFuture(),
            'past' => $event->ends_at->isPast(),
            'registered' => $event->registrations_count ?? $event->registrations()->count(),
            'unpaid' => $unpaid,
        ];
    }
}
