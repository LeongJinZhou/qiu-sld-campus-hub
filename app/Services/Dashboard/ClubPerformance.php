<?php

namespace App\Services\Dashboard;

use App\Enums\DocumentStatus;
use App\Enums\MembershipStatus;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventDocumentItem;
use App\Models\Membership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How well each club is doing in a session, for SLD. Five measures, each scored against the best club
 * (100 = the leader), then weighted into one score:
 *   events run 25 · attendance 25 · active members 15 · engagement (likes + comments) 15 · post-event documents on time 20.
 * Drill up: clubs grouped by category. Drill down: one club month by month, then its events.
 */
class ClubPerformance
{
    public const WEIGHTS = ['events' => 25, 'attendance' => 25, 'members' => 15, 'engagement' => 15, 'on_time' => 20];

    /** @return list<array<string, mixed>> every club with its measures and score, best first */
    public function clubs(AcademicSession $session): array
    {
        [$from, $to] = $this->range($session);
        $clubs = Club::with('category')->orderBy('name')->get();
        $events = Event::where('status', DocumentStatus::Approved)->whereBetween('starts_at', [$from, $to])->get(['id', 'club_id']);
        $eventIds = $events->pluck('id');
        $attendance = DB::table('attendance')->whereIn('event_id', $eventIds)->whereNotNull('clock_in_at')
            ->join('events', 'events.id', '=', 'attendance.event_id')->selectRaw('events.club_id, COUNT(*) AS n')->groupBy('events.club_id')->pluck('n', 'club_id');
        $registered = DB::table('event_registrations')->whereIn('event_id', $eventIds)->whereNull('event_registrations.deleted_at')
            ->join('events', 'events.id', '=', 'event_registrations.event_id')->selectRaw('events.club_id, COUNT(*) AS n')->groupBy('events.club_id')->pluck('n', 'club_id');
        $members = Membership::whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace])->selectRaw('club_id, COUNT(*) AS n')->groupBy('club_id')->pluck('n', 'club_id');
        $likes = DB::table('post_likes')->join('posts', 'posts.id', '=', 'post_likes.post_id')->whereBetween('post_likes.created_at', [$from, $to])
            ->selectRaw('posts.club_id, COUNT(*) AS n')->groupBy('posts.club_id')->pluck('n', 'club_id');
        $comments = DB::table('comments')->join('posts', 'posts.id', '=', 'comments.post_id')->whereNull('comments.hidden_at')->whereBetween('comments.created_at', [$from, $to])
            ->selectRaw('posts.club_id, COUNT(*) AS n')->groupBy('posts.club_id')->pluck('n', 'club_id');
        $docs = EventDocumentItem::whereIn('event_id', $eventIds)->whereDate('due_on', '<=', min(today(), $to))->with('event:id,club_id')->get()
            ->groupBy(fn (EventDocumentItem $d) => $d->event->club_id);

        $rows = $clubs->map(function (Club $club) use ($events, $attendance, $registered, $members, $likes, $comments, $docs): array {
            $due = $docs->get($club->id, collect());
            $onTime = $due->filter(fn (EventDocumentItem $d) => $d->submitted_at && $d->submitted_at->toDateString() <= $d->due_on->toDateString())->count();

            return [
                'slug' => $club->slug, 'name' => $club->name, 'category' => $club->category?->name ?? 'Uncategorised', 'status' => $club->status,
                'events' => $events->where('club_id', $club->id)->count(),
                'attendance' => (int) ($attendance[$club->id] ?? 0),
                'registered' => (int) ($registered[$club->id] ?? 0),
                'members' => (int) ($members[$club->id] ?? 0),
                'engagement' => (int) ($likes[$club->id] ?? 0) + (int) ($comments[$club->id] ?? 0),
                // No documents due yet counts as on time.
                'on_time' => $due->isEmpty() ? 100 : (int) round($onTime / $due->count() * 100),
            ];
        });

        return $this->score($rows)->sortByDesc('score')->values()->map(fn (array $r, int $i) => [...$r, 'rank' => $i + 1])->all();
    }

    /** Drill up: one row per category (totals, average score, best club). @return list<array<string, mixed>> */
    public function categories(array $clubs): array
    {
        return collect($clubs)->groupBy('category')->map(fn (Collection $g, string $category) => [
            'category' => $category, 'clubs' => $g->count(),
            'events' => $g->sum('events'), 'attendance' => $g->sum('attendance'), 'members' => $g->sum('members'), 'engagement' => $g->sum('engagement'),
            'score' => (int) round($g->avg('score')), 'best' => $g->sortByDesc('score')->first()['name'],
        ])->sortByDesc('score')->values()->all();
    }

    /** Drill down: one club month by month across the session, and its events. @return array<string, mixed> */
    public function club(Club $club, AcademicSession $session): array
    {
        [$from, $to] = $this->range($session);
        $events = Event::where('club_id', $club->id)->where('status', DocumentStatus::Approved)->whereBetween('starts_at', [$from, $to])
            ->withCount(['registrations', 'attendance as attended_count' => fn ($q) => $q->whereNotNull('clock_in_at')])->orderBy('starts_at')->get();
        $months = [];
        for ($m = $from->startOfMonth(); $m->lte($to); $m = $m->addMonth()) {
            $in = $events->filter(fn (Event $e) => $e->starts_at->format('Y-m') === $m->format('Y-m'));
            $months[] = ['key' => $m->format('Y-m'), 'label' => $m->format('M Y'), 'events' => $in->count(), 'attendance' => (int) $in->sum('attended_count'), 'registered' => (int) $in->sum('registrations_count')];
        }

        return [
            'months' => $months,
            'events' => $events->map(fn (Event $e) => [
                'slug' => $e->slug, 'name' => $e->name, 'date' => $e->starts_at->toDateString(), 'month' => $e->starts_at->format('Y-m'),
                'registered' => $e->registrations_count, 'attended' => $e->attended_count,
                'rate' => $e->registrations_count ? (int) round($e->attended_count / $e->registrations_count * 100) : null,
            ])->values()->all(),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function score(Collection $rows): Collection
    {
        $max = collect(array_keys(self::WEIGHTS))->mapWithKeys(fn (string $k) => [$k => max(1, (int) $rows->max($k))]);

        return $rows->map(function (array $r) use ($max): array {
            $parts = collect(self::WEIGHTS)->map(fn (int $w, string $k) => $k === 'on_time' ? $r[$k] / 100 * $w : $r[$k] / $max[$k] * $w);

            return [...$r, 'score' => (int) round($parts->sum()), 'parts' => $parts->map(fn ($v) => round($v, 1))->all()];
        });
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function range(AcademicSession $session): array
    {
        return [CarbonImmutable::parse($session->starts_on)->startOfDay(), CarbonImmutable::parse($session->ends_on)->endOfDay()];
    }
}
