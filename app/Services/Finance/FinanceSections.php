<?php

namespace App\Services\Finance;

use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\MembershipDrive;
use App\Models\MembershipPayment;
use Illuminate\Support\Collection;

/**
 * The club's money split the way committees read it: membership fees taken during a membership drive, normal
 * (outside a drive) registrations, each event on its own, and everything else. One session, or the club's
 * whole history.
 */
class FinanceSections
{
    /** @var array<int, Collection<int, MembershipDrive>> */
    private array $drives = [];

    public function __construct(private readonly Ledger $ledger) {}

    /**
     * @return array{period: string, brought_forward_sen: int, closing_sen: int, sections: list<array{key: string, title: string, rows: list<array<string, mixed>>}>}
     */
    public function build(Club $club, ?AcademicSession $session): array
    {
        $all = collect($this->ledger->rows($club));
        $opening = (int) ($club->openingBalance->amount_sen ?? 0);
        $from = $session?->starts_on->toDateString() ?? '0000-00-00';
        $to = $session?->ends_on->toDateString() ?? '9999-12-31';
        $entries = $all->where('category', '!=', 'opening');
        $inPeriod = $entries->filter(fn (array $r) => $r['date'] >= $from && $r['date'] <= $to)->values();
        $broughtForward = $opening + $entries->filter(fn (array $r) => $r['date'] < $from)->sum(fn (array $r) => $r['income_sen'] - $r['expense_sen']);

        $people = MembershipPayment::with('user.student')->whereIn('id', $inPeriod->pluck('payment_id')->filter())->get()->keyBy('id');
        $rows = $inPeriod->map(function (array $r) use ($people, $club): array {
            $payment = $r['payment_id'] ? $people->get($r['payment_id']) : null;

            return [
                ...$r,
                'name' => $payment?->user->name,
                'student_number' => $payment?->user->student?->student_number,
                'drive' => $r['category'] === 'membership' && $payment ? $this->driveOf($club, $payment)?->title : null,
            ];
        });

        $membership = $rows->where('category', 'membership');
        $sections = [
            ['key' => 'drive', 'title' => 'Membership drive', 'rows' => $membership->whereNotNull('drive')->values()->all()],
            ['key' => 'normal', 'title' => 'Normal registration', 'rows' => $membership->whereNull('drive')->values()->all()],
        ];
        foreach ($rows->where('category', 'event')->whereNotNull('event_id')->groupBy('event_id')->sortBy(fn (Collection $g) => $g->min('date')) as $id => $group) {
            $sections[] = ['key' => "event:{$id}", 'title' => (string) $group->first()['event'], 'rows' => $group->values()->all()];
        }
        $sections[] = ['key' => 'other', 'title' => 'Other transactions', 'rows' => $rows->reject(fn (array $r) => $r['category'] === 'membership' || ($r['category'] === 'event' && $r['event_id']))->values()->all()];

        return [
            'period' => $session ? "Session {$session->name}" : 'All transactions to date',
            'brought_forward_sen' => $broughtForward,
            'closing_sen' => $broughtForward + $inPeriod->sum(fn (array $r) => $r['income_sen'] - $r['expense_sen']),
            'sections' => $sections,
        ];
    }

    /** The sections a committee can tick for an export: key and title, with how many transactions each has. @return list<array{key: string, title: string, count: int}> */
    public function choices(Club $club, ?AcademicSession $session): array
    {
        return array_map(fn (array $s) => ['key' => $s['key'], 'title' => $s['title'], 'count' => count($s['rows'])], $this->build($club, $session)['sections']);
    }

    private function driveOf(Club $club, MembershipPayment $payment): ?MembershipDrive
    {
        $this->drives[$club->id] ??= MembershipDrive::where(fn ($q) => $q->whereNull('club_id')->orWhere('club_id', $club->id))->orderBy('starts_at')->get();
        $at = $payment->verified_at ?? $payment->created_at;

        return $this->drives[$club->id]->first(fn (MembershipDrive $d) => $at->between($d->starts_at, $d->ends_at));
    }
}
