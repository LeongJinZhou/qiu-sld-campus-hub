<?php

namespace App\Services\Dashboard;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\AcademicSession;
use App\Models\Agm;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\EventDocumentItem;
use App\Models\LedgerEntry;
use App\Models\Meeting;
use App\Services\Reports\EvidencePhotos;
use App\Services\SldRules;
use Carbon\CarbonImmutable;

/**
 * SLD's cross-club tracker for a session: what each club has submitted and what is missing or overdue —
 * weekly activity reports month by month, each finished event's completion package (post-event documents and
 * photos), the AGM, and approved withdrawals whose transfer is past the deadline. Plus monthly and yearly money.
 */
class ClubTracking
{
    public function __construct(private readonly EvidencePhotos $photos, private readonly SldRules $rules) {}

    /** @return array<string, mixed> */
    public function build(AcademicSession $session): array
    {
        $from = CarbonImmutable::parse($session->starts_on)->startOfDay();
        $to = CarbonImmutable::parse($session->ends_on)->endOfDay();
        $months = collect();
        for ($m = $from->startOfMonth(); $m->lte(min($to, CarbonImmutable::now()->endOfMonth())); $m = $m->addMonth()) {
            $months->push(['key' => $m->format('Y-m'), 'label' => $m->format('M')]);
        }
        $clubs = Club::orderBy('name')->get();
        $meetings = Meeting::with('minutes')->where('session_id', $session->id)->where('kind', 'activity')->where('ends_at', '<', now())->get()->groupBy('club_id');
        $events = Event::with(['documentItems', 'club'])->where('session_id', $session->id)->where('status', DocumentStatus::Approved)->where('ends_at', '<', now())->get()->groupBy('club_id');
        $agms = Agm::with('report')->where('session_id', $session->id)->get()->keyBy('club_id');

        $rows = $clubs->map(function (Club $club) use ($months, $meetings, $events, $agms): array {
            $held = $meetings->get($club->id, collect());
            $weekly = $months->map(function (array $m) use ($held): array {
                $inMonth = $held->filter(fn (Meeting $mt) => $mt->starts_at->format('Y-m') === $m['key']);
                $submitted = $inMonth->filter(fn (Meeting $mt) => in_array($mt->minutes?->status, [DocumentStatus::InReview, DocumentStatus::Approved], true))->count();

                return ['key' => $m['key'], 'held' => $inMonth->count(), 'submitted' => $submitted];
            })->values()->all();
            $done = $events->get($club->id, collect())->map(fn (Event $e) => $this->eventPackage($e));
            $agm = $agms->get($club->id);

            return [
                'slug' => $club->slug, 'name' => $club->name,
                'weekly' => $weekly,
                'weekly_missing' => collect($weekly)->sum(fn ($w) => $w['held'] - $w['submitted']),
                'events' => $done->values()->all(),
                'events_incomplete' => $done->where('complete', false)->count(),
                'agm' => $agm ? ['report' => $agm->report?->status->value ?? 'not started', 'package' => $agm->package_submitted_at !== null, 'acknowledged' => $agm->acknowledged_at !== null] : null,
            ];
        });

        return [
            'session' => $session->name,
            'months' => $months->values()->all(),
            'clubs' => $rows->values()->all(),
            'transfers' => $this->overdueTransfers(),
            'totals' => [
                'weekly_missing' => $rows->sum('weekly_missing'),
                'events_incomplete' => $rows->sum('events_incomplete'),
                'agm_missing' => $rows->filter(fn ($r) => ! ($r['agm']['package'] ?? false))->count(),
            ],
        ];
    }

    /**
     * A finished event's completion package: every post-event document submitted and SLD's minimum photos.
     *
     * @return array{slug: string, name: string, date: string, missing: list<string>, overdue: bool, complete: bool}
     */
    public function eventPackage(Event $event): array
    {
        $missing = $event->documentItems->whereNull('submitted_at')->map(fn (EventDocumentItem $i) => $i->label())->values()->all();
        if (! $this->photos->enough($event)) {
            $missing[] = 'Photos ('.$this->photos->minimum().')';
        }

        return [
            'slug' => $event->slug, 'name' => $event->name, 'date' => $event->starts_at->toDateString(), 'missing' => $missing,
            'overdue' => $event->documentItems->contains(fn (EventDocumentItem $i) => ! $i->submitted_at && $i->due_on->endOfDay()->isPast()),
            'complete' => $missing === [],
        ];
    }

    /**
     * Approved withdrawals Finance has not finished transferring within the deadline (SLD setting, default 14 days).
     *
     * @return list<array<string, mixed>>
     */
    public function overdueTransfers(): array
    {
        $days = $this->rules->get('transfer_days');

        return Document::with('club', 'event')->where('type', DocumentType::FundWithdrawal)->where('status', DocumentStatus::Approved)
            ->whereIn('finance_stage', ['awaiting_finance', 'awaiting_bankin'])->whereNotNull('decided_at')->get()
            ->map(fn (Document $d) => ['id' => $d->id, 'club' => $d->club->name, 'for' => $d->event?->name ?? ($d->data()['purpose'] ?? 'Club'),
                'total_sen' => (int) ($d->data()['total_sen'] ?? 0), 'stage' => $d->finance_stage,
                'due_on' => $d->decided_at->copy()->addDays($days)->toDateString(), 'days_late' => (int) max(0, $d->decided_at->copy()->addDays($days)->startOfDay()->diffInDays(today(), false))])
            ->filter(fn (array $t) => $t['days_late'] > 0)->sortByDesc('days_late')->values()->all();
    }

    /**
     * Income and expenses month by month (one session) and year by year (all sessions), all clubs or one club.
     * Withdrawals are advances, not expenses, so they are left out.
     *
     * @return array{monthly: list<array{key: string, label: string, income_sen: int, expense_sen: int}>, yearly: list<array{key: string, income_sen: int, expense_sen: int}>}
     */
    public function money(AcademicSession $session, ?Club $club = null): array
    {
        $entries = LedgerEntry::query()->when($club, fn ($q) => $q->where('club_id', $club->id))->where('category', '!=', 'withdrawal')->get(['entry_date', 'income_sen', 'expense_sen']);
        $from = CarbonImmutable::parse($session->starts_on);
        $monthly = collect(range(0, 11))->map(function (int $i) use ($from, $entries): array {
            $m = $from->startOfMonth()->addMonths($i);
            $in = $entries->filter(fn ($e) => $e->entry_date->format('Y-m') === $m->format('Y-m'));

            return ['key' => $m->format('Y-m'), 'label' => $m->format('M y'), 'income_sen' => (int) $in->sum('income_sen'), 'expense_sen' => (int) $in->sum('expense_sen')];
        })->all();
        $yearly = AcademicSession::orderBy('starts_on')->where('starts_on', '<=', now())->get()->map(function (AcademicSession $s) use ($entries): array {
            $in = $entries->filter(fn ($e) => $e->entry_date->between($s->starts_on, $s->ends_on));

            return ['key' => $s->name, 'income_sen' => (int) $in->sum('income_sen'), 'expense_sen' => (int) $in->sum('expense_sen')];
        })->values()->all();

        return ['monthly' => $monthly, 'yearly' => $yearly];
    }
}
