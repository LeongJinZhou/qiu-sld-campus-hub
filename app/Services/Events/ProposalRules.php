<?php

namespace App\Services\Events;

use App\Models\Setting;
use App\Services\SldRules;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;

/** Lead-time, budget, exception and CCA rules for event proposals. */
class ProposalRules
{
    /** Earliest event date for a proposal submitted on $submittedOn: SLD's lead time (default 14 days) ahead. */
    public function earliestStart(CarbonInterface $submittedOn, ?int $leadDays = null): CarbonInterface
    {
        return $submittedOn->copy()->startOfDay()->addDays($leadDays ?? app(SldRules::class)->get('proposal_lead_days'));
    }

    /**
     * A proposal inside the lead time needs a reason; SLD then decides (it is shown to SLD as an exception to approve).
     */
    public function assertLeadTime(CarbonInterface $startsAt, CarbonInterface $submittedOn, ?string $lateReason, ?int $leadDays = null): void
    {
        $earliest = $this->earliestStart($submittedOn, $leadDays);
        if ($startsAt->lt($earliest) && trim((string) $lateReason) === '') {
            $days = $leadDays ?? app(SldRules::class)->get('proposal_lead_days');
            throw new DomainException("Proposals are submitted at least {$days} days before the event (earliest {$earliest->format('j M Y')}). To submit later, give a reason; SLD decides.");
        }
    }

    /**
     * Exceptions SLD must approve explicitly: a late submission and a budget that does not break even, each with the
     * committee's reason.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{kind: string, label: string, reason: string}>
     */
    public function exceptions(array $data, CarbonInterface $submittedOn, ?int $leadDays = null): array
    {
        $out = [];
        $starts = rescue(fn () => CarbonImmutable::parse((string) ($data['starts_at'] ?? '')), null, false);
        if ($starts && ($data['starts_at'] ?? '') !== '' && $starts->lt($this->earliestStart($submittedOn, $leadDays))) {
            $out[] = ['kind' => 'late', 'label' => 'Submitted inside the '.($leadDays ?? app(SldRules::class)->get('proposal_lead_days')).'-day lead time', 'reason' => (string) ($data['late_reason'] ?? '')];
        }
        $totals = $this->budgetTotals((array) ($data['budget'] ?? []));
        if (! $totals['covers_expenses']) {
            $out[] = ['kind' => 'deficit', 'label' => 'Budget deficit of RM '.number_format(-$totals['profit_sen'] / 100, 2), 'reason' => (string) ($data['deficit_reason'] ?? '')];
        }

        return $out;
    }

    /**
     * @param  array{revenue?: list<array{price_sen: int, quantity: int}>, expenses?: list<array{price_sen: int, quantity: int}>}  $budget
     * @return array{revenue_sen: int, expenses_sen: int, profit_sen: int, covers_expenses: bool}
     */
    public function budgetTotals(array $budget): array
    {
        $sum = fn (array $items) => array_sum(array_map(fn (array $i) => (int) $i['price_sen'] * (int) $i['quantity'], $items));
        $revenue = $sum($budget['revenue'] ?? []);
        $expenses = $sum($budget['expenses'] ?? []);

        return [
            'revenue_sen' => $revenue,
            'expenses_sen' => $expenses,
            'profit_sen' => $revenue - $expenses,
            'covers_expenses' => $revenue >= $expenses,
        ];
    }

    public const CCA_SETTING = 'cca_limit';

    public const DEFAULT_CCA_LIMIT = 10;

    /** 1 hour of event = 1 CCA; the proposal form caps it at SLD's limit. */
    public function defaultCca(CarbonInterface $startsAt, CarbonInterface $endsAt): float
    {
        return round($startsAt->diffInMinutes($endsAt, absolute: true) / 60, 1);
    }

    /** The most CCA hours one event may award; only SLD admins change it (Settings). */
    public function ccaLimit(): float
    {
        return (float) (Setting::read(self::CCA_SETTING, ['max_hours' => self::DEFAULT_CCA_LIMIT])['max_hours'] ?? self::DEFAULT_CCA_LIMIT);
    }
}
