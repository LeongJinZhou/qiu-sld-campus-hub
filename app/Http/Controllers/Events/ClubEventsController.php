<?php

namespace App\Http\Controllers\Events;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every event of a club the user runs: happening now, coming up (approved or still in approval), and past events
 * by year. Drafts and rejected proposals stay on the requests board instead.
 */
class ClubEventsController extends Controller
{
    private const SHOWN = [DocumentStatus::InReview, DocumentStatus::Returned, DocumentStatus::Approved];

    public function __invoke(Request $request, Club $club): Response
    {
        Gate::authorize('viewInternal', $club);
        $now = now();
        $events = fn (): Builder => Event::query()->where('club_id', $club->id)->whereIn('status', self::SHOWN)->withCount('registrations');

        // Past events count only once approved (an unapproved one never ran).
        $pastYears = Event::where('club_id', $club->id)->where('status', DocumentStatus::Approved)->where('ends_at', '<=', $now)
            ->pluck('starts_at')->map(fn ($at) => (int) $at->format('Y'))->unique()->sortDesc()->values();
        $year = (int) $request->query('year', (string) ($pastYears->first() ?? $now->year));
        if (! $pastYears->contains($year)) {
            $year = (int) ($pastYears->first() ?? $now->year);
        }

        return Inertia::render('Events/ClubEvents', [
            'club' => $club->only('slug', 'name'),
            'ongoing' => $events()->where('starts_at', '<=', $now)->where('ends_at', '>', $now)->orderBy('ends_at')->get()->map(fn (Event $e) => $this->card($e)),
            'upcoming' => $events()->where('starts_at', '>', $now)->orderBy('starts_at')->get()->map(fn (Event $e) => $this->card($e)),
            'past' => $events()->where('status', DocumentStatus::Approved)->where('ends_at', '<=', $now)
                ->whereBetween('starts_at', [now()->setDate($year, 1, 1)->startOfDay(), now()->setDate($year, 12, 31)->endOfDay()])
                ->orderByDesc('starts_at')->get()->map(fn (Event $e) => $this->card($e)),
            'years' => $pastYears,
            'year' => $year,
        ]);
    }

    /** @return array<string, mixed> */
    private function card(Event $event): array
    {
        return [
            'slug' => $event->slug, 'name' => $event->name, 'venue' => $event->venue, 'poster_url' => $event->posterUrl(),
            'starts_at' => $event->starts_at->toIso8601String(), 'ends_at' => $event->ends_at->toIso8601String(),
            'status' => $event->status->value, 'registered' => $event->registrations_count,
        ];
    }
}
