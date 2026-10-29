<?php

namespace App\Services\Membership;

use App\Enums\PaymentStatus;
use App\Enums\ScopeType;
use App\Models\Attendance;
use App\Models\CcaRecord;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\RoleAssignment;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Members list for committee, advisor and SLD (CLAUDE.md §6): search, combinable filters, multi-level sort.
 * Rows are built per club scope; club sizes are small enough to filter in memory with full flexibility.
 */
class MembersQuery
{
    public const SORTABLE = ['name', 'student_number', 'programme', 'year_group', 'status', 'joined', 'expires', 'events', 'cca', 'club'];

    public function __construct(private readonly SessionResolver $sessions) {}

    /**
     * @param  list<int>|null  $clubIds  null = all clubs (SLD)
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(?array $clubIds, array $filters = []): Collection
    {
        $memberships = Membership::with(['user.student', 'club.category'])
            ->when($clubIds !== null, fn ($q) => $q->whereIn('club_id', $clubIds))
            ->get();
        $userIds = $memberships->pluck('user_id')->unique()->all();
        $clubIdsInScope = $memberships->pluck('club_id')->unique()->all();

        $latestPayments = MembershipPayment::with('collector')->whereIn('membership_id', $memberships->pluck('id'))
            ->orderBy('id')->get()->keyBy('membership_id');
        $positions = RoleAssignment::where('scope_type', ScopeType::Club)->whereIn('scope_id', $clubIdsInScope)
            ->where('session_id', $this->sessions->current()->id)->whereIn('user_id', $userIds)->get()
            ->mapWithKeys(fn (RoleAssignment $a) => ["{$a->scope_id}:{$a->user_id}" => $a->position ?? 'Advisor']);
        $events = Attendance::whereIn('user_id', $userIds)->whereNotNull('clock_in_at')
            ->join('events', 'events.id', '=', 'attendance.event_id')->whereIn('events.club_id', $clubIdsInScope)
            ->selectRaw('attendance.user_id, events.club_id, count(*) as n')->groupBy('attendance.user_id', 'events.club_id')->get()
            ->mapWithKeys(fn ($r) => ["{$r->club_id}:{$r->user_id}" => (int) $r->n]);
        $cca = CcaRecord::whereIn('user_id', $userIds)->selectRaw('user_id, sum(hours) as h')->groupBy('user_id')->pluck('h', 'user_id');

        $rows = $memberships->map(function (Membership $m) use ($latestPayments, $positions, $events, $cca) {
            $student = $m->user->student;
            $payment = $latestPayments[$m->id] ?? null;
            $key = "{$m->club_id}:{$m->user_id}";

            return [
                'id' => $m->id,
                'club' => $m->club->name,
                'club_id' => $m->club_id,
                'category' => $m->club->category?->name,
                'club_status' => $m->club->status,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'student_number' => $student?->student_number,
                'programme' => $student?->programme,
                'programme_short' => $student?->programmeShortForm(),
                'year_group' => $student?->year_group,
                'intake' => $student ? sprintf('%d%02d', $student->year_entry, $student->intake_month) : null,
                'status' => $m->status->value,
                'position' => $positions[$key] ?? 'Member',
                'joined' => $m->starts_on?->toDateString(),
                'expires' => $m->expires_on?->toDateString(),
                'payment' => $this->paymentState($payment),
                'method' => $payment?->method,
                'collected_by' => $payment?->collector?->name,
                'events' => $events[$key] ?? 0,
                'cca' => round((float) ($cca[$m->user_id] ?? 0), 1),
            ];
        });

        return $this->sort($this->filter($rows, $filters), $filters['sort'] ?? [['name', 'asc']]);
    }

    /** @return array<string, int> dashboard cards */
    public function counts(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'active' => $rows->where('status', 'active')->count(),
            'grace' => $rows->where('status', 'grace')->count(),
            'terminated' => $rows->where('status', 'terminated')->count(),
            'alumni' => $rows->where('status', 'alumni')->count(),
            'paid' => $rows->where('payment', 'paid')->count(),
            'pending' => $rows->where('payment', 'pending')->count(),
            'unpaid' => $rows->where('payment', 'unpaid')->count(),
        ];
    }

    private function paymentState(?MembershipPayment $payment): string
    {
        return match (true) {
            $payment === null => 'unpaid',
            $payment->status->isPaid() => 'paid',
            in_array($payment->status, [PaymentStatus::Reading, PaymentStatus::PendingReview, PaymentStatus::CashPending], true) => 'pending',
            default => 'unpaid',
        };
    }

    /** @param array<string, mixed> $f */
    private function filter(Collection $rows, array $f): Collection
    {
        $search = mb_strtolower(trim((string) ($f['search'] ?? '')));
        $in = fn (string $key) => array_filter((array) ($f[$key] ?? []), fn ($v) => $v !== '' && $v !== null);
        $session = ! empty($f['session']) ? $this->sessions->forName((string) $f['session']) : null;
        $expiringDays = isset($f['expiring_days']) && $f['expiring_days'] !== '' ? (int) $f['expiring_days'] : null;
        $today = CarbonImmutable::today();

        return $rows->filter(function (array $r) use ($f, $search, $in, $session, $expiringDays, $today) {
            if ($search !== '' && ! str_contains(mb_strtolower(implode(' ', [$r['name'], $r['student_number'], $r['email'], $r['programme'], $r['programme_short']])), $search)) {
                return false;
            }
            foreach (['status', 'position', 'payment', 'method', 'year_group', 'programme_short', 'intake', 'club_id', 'category', 'club_status', 'collected_by'] as $key) {
                $values = $in($key);
                if ($values !== [] && ! in_array((string) $r[$key], array_map('strval', $values), true)) {
                    return false;
                }
            }
            if ($session && (! $r['joined'] || $r['joined'] > $session->ends_on->toDateString() || ($r['expires'] && $r['expires'] < $session->starts_on->toDateString()))) {
                return false;
            }
            if (! empty($f['joined_from']) && (! $r['joined'] || $r['joined'] < $f['joined_from'])) {
                return false;
            }
            if (! empty($f['joined_to']) && (! $r['joined'] || $r['joined'] > $f['joined_to'])) {
                return false;
            }
            if ($expiringDays !== null && (! $r['expires'] || $r['status'] !== 'active' || $r['expires'] > $today->addDays($expiringDays)->toDateString())) {
                return false;
            }
            if (isset($f['min_events']) && $f['min_events'] !== '' && $r['events'] < (int) $f['min_events']) {
                return false;
            }

            return ! (isset($f['min_cca']) && $f['min_cca'] !== '' && $r['cca'] < (float) $f['min_cca']);
        })->values();
    }

    /** @param list<array{0: string, 1: string}> $sort multi-level, e.g. [['position', 'asc'], ['name', 'asc']] */
    private function sort(Collection $rows, array $sort): Collection
    {
        $levels = array_values(array_filter($sort, fn ($s) => is_array($s) && in_array($s[0] ?? null, self::SORTABLE, true)));

        return $rows->sort(function (array $a, array $b) use ($levels) {
            foreach ($levels as [$field, $dir]) {
                $cmp = is_numeric($a[$field]) && is_numeric($b[$field]) ? $a[$field] <=> $b[$field] : strnatcasecmp((string) $a[$field], (string) $b[$field]);
                if ($cmp !== 0) {
                    return $dir === 'desc' ? -$cmp : $cmp;
                }
            }

            return 0;
        })->values();
    }
}
