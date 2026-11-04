<?php

namespace App\Services\Meetings;

use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\Meeting;
use App\Services\Documents\DocumentExporter;
use App\Support\QueryInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Committee meetings and weekly activities as a report: who was invited, who came (by QR or ticked by hand), who
 * checked out, and where the minutes stand. One club's report for its committee; every club's for SLD.
 */
class MeetingReport
{
    public const HEADERS = ['Date', 'Club', 'Type', 'Title', 'For', 'Venue', 'Pax planned', 'Invited', 'Present', 'Checked out', 'Attendance %', 'Minutes'];

    public function __construct(private readonly Meetings $meetings) {}

    /** @return array{kind: string, month: string, club: string} */
    public function filters(Request $request): array
    {
        return [
            'kind' => QueryInput::oneOf($request, 'kind', array_keys(Meeting::KINDS)) ?? '',
            'month' => preg_match('/^\d{4}-\d{2}$/', $m = QueryInput::text($request, 'month', 7)) ? $m : '',
            'club' => QueryInput::text($request, 'club', 100),
        ];
    }

    /** @param array{kind: string, month: string, club: string} $filters */
    public function query(AcademicSession $session, array $filters, ?Club $club = null): Builder
    {
        return Meeting::query()->with(['club', 'minutes.latestVersion'])->withCount([
            'attendees as present_count' => fn (Builder $b) => $b->where('meeting_attendance.present', true),
            'attendees as out_count' => fn (Builder $b) => $b->whereNotNull('meeting_attendance.checked_out_at'),
            'attendees as recorded_count',
        ])
            ->where('session_id', $session->id)
            ->when($club, fn (Builder $b) => $b->where('club_id', $club->id))
            ->when(! $club && $filters['club'] !== '', fn (Builder $b) => $b->whereHas('club', fn (Builder $c) => $c->where('slug', $filters['club'])))
            ->when($filters['kind'] !== '', fn (Builder $b) => $b->where('kind', $filters['kind']))
            ->when($filters['month'] !== '', fn (Builder $b) => $b->whereBetween('starts_at', [
                now()->parse($filters['month'].'-01')->startOfMonth(), now()->parse($filters['month'].'-01')->endOfMonth(),
            ]))
            ->orderByDesc('starts_at');
    }

    /** @return array<string, mixed> */
    public function row(Meeting $m): array
    {
        // Once attendance is taken every invitee has a row; before that, count who the meeting is for today.
        $invited = $m->recorded_count ?: $this->meetings->invitees($m)->count();
        $audience = $m->kind === 'activity' ? $m->audience : 'committee';

        return [
            'id' => $m->id,
            'date' => $m->starts_at->toIso8601String(),
            'club' => $m->club->name,
            'club_slug' => $m->club->slug,
            'kind' => Meeting::KINDS[$m->kind] ?? $m->kind,
            'title' => $m->title,
            'audience' => Meeting::AUDIENCES[$audience] ?? $audience,
            'venue' => $m->venue ?? ($m->meet_link ? 'Google Meet' : null),
            'pax' => $m->pax,
            'invited' => $invited,
            'present' => (int) $m->present_count,
            'checked_out' => (int) $m->out_count,
            'rate' => $invited > 0 ? (int) round($m->present_count / $invited * 100) : 0,
            'minutes' => $m->minutes ? Str::headline($m->minutes->status->value) : 'None',
            'minutes_id' => $m->minutes?->id,
            'minutes_files' => $m->minutes ? app(DocumentExporter::class)->formats($m->minutes) : [],
            'upcoming' => $m->starts_at->isFuture(),
        ];
    }

    /** @param array<string, mixed> $r @return list<mixed> */
    public function line(array $r): array
    {
        return [
            now()->parse($r['date'])->format('j M Y, g:i A'), $r['club'], $r['kind'], $r['title'], $r['audience'], $r['venue'] ?? '-',
            $r['pax'] ?? '-', $r['invited'], $r['present'], $r['checked_out'], $r['upcoming'] ? '-' : $r['rate'], $r['minutes'],
        ];
    }

    /**
     * Totals for the header cards.
     *
     * @param  iterable<array<string, mixed>>  $rows
     * @return array{meetings: int, committee: int, activities: int, average_rate: int, minutes_approved: int}
     */
    public function totals(iterable $rows): array
    {
        $rows = collect($rows);
        $held = $rows->where('upcoming', false);

        return [
            'meetings' => $rows->count(),
            'committee' => $rows->where('kind', Meeting::KINDS['committee'])->count(),
            'activities' => $rows->where('kind', Meeting::KINDS['activity'])->count(),
            'average_rate' => $held->count() ? (int) round($held->avg('rate')) : 0,
            'minutes_approved' => $rows->where('minutes', 'Approved')->count(),
        ];
    }
}
