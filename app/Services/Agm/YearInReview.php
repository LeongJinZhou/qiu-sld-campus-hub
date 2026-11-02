<?php

namespace App\Services\Agm;

use App\Enums\DocumentStatus;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\Event;
use App\Models\LedgerEntry;
use App\Models\Meeting;
use App\Services\Dashboard\ClubTracking;

/**
 * Everything a club did in one session, compiled for its AGM: the events (with turnout and whether each
 * completion package is in), weekly activities held and reported, and the year's money month by month and by
 * category. Strictly that session: the next committee's year is compiled separately.
 */
class YearInReview
{
    public function __construct(private readonly ClubTracking $tracking) {}

    /** @return array<string, mixed> */
    public function for(Club $club, AcademicSession $session): array
    {
        $from = $session->starts_on->copy()->startOfDay();
        $to = $session->ends_on->copy()->endOfDay();

        $events = Event::with('documentItems', 'club')->withCount(['registrations', 'attendance' => fn ($q) => $q->whereNotNull('clock_in_at')])
            ->where('club_id', $club->id)->where('session_id', $session->id)->where('status', DocumentStatus::Approved)->orderBy('starts_at')->get()
            ->map(fn (Event $e) => [
                'slug' => $e->slug, 'name' => $e->name, 'date' => $e->starts_at->toDateString(),
                'registered' => $e->registrations_count, 'attended' => $e->attendance_count,
                'package' => $e->ends_at->isPast() ? $this->tracking->eventPackage($e)['complete'] : null,
            ])->values();

        $weekly = Meeting::with('minutes')->where('club_id', $club->id)->where('session_id', $session->id)->where('kind', 'activity')->get();
        $reported = $weekly->filter(fn (Meeting $m) => in_array($m->minutes?->status, [DocumentStatus::InReview, DocumentStatus::Approved], true))->count();

        $entries = LedgerEntry::where('club_id', $club->id)->where('category', '!=', 'withdrawal')->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])->get();
        $byCategory = $entries->groupBy(fn (LedgerEntry $e) => $e->category ?: 'other')
            ->map(fn ($rows, $category) => ['category' => (string) $category, 'income_sen' => (int) $rows->sum('income_sen'), 'expense_sen' => (int) $rows->sum('expense_sen')])
            ->sortByDesc(fn ($r) => $r['income_sen'] + $r['expense_sen'])->values();

        return [
            'session' => $session->name,
            'session_id' => $session->id,
            'events' => $events,
            'weekly' => ['held' => $weekly->where('ends_at', '<', now())->count(), 'reported' => $reported],
            'finance' => [
                'income_sen' => (int) $entries->sum('income_sen'),
                'expense_sen' => (int) $entries->sum('expense_sen'),
                'monthly' => $this->tracking->money($session, $club)['monthly'],
                'categories' => $byCategory,
            ],
            'members' => $club->memberships()->whereIn('status', ['active', 'grace'])->count(),
        ];
    }
}
