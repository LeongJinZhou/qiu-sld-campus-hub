<?php

namespace App\Services\Events;

use Carbon\CarbonInterface;
use DomainException;

/** Lead-time, budget and CCA rules for event proposals. */
class ProposalRules
{
    public const LARGE_EVENT_PARTICIPANTS = 60;

    /** < 60 participants: 1.5 months ahead; 60 or more: 3 months ahead. */
    public function earliestStart(int $expectedParticipants, CarbonInterface $submittedOn): CarbonInterface
    {
        $date = $submittedOn->copy()->startOfDay();

        return $expectedParticipants >= self::LARGE_EVENT_PARTICIPANTS
            ? $date->addMonthsNoOverflow(3)
            : $date->addMonthsNoOverflow(1)->addDays(15);
    }

    public function assertLeadTime(int $expectedParticipants, CarbonInterface $startsAt, CarbonInterface $submittedOn): void
    {
        $earliest = $this->earliestStart($expectedParticipants, $submittedOn);
        if ($startsAt->lt($earliest)) {
            $lead = $expectedParticipants >= self::LARGE_EVENT_PARTICIPANTS ? '3 months' : '1.5 months';
            throw new DomainException("Events with this many participants must be submitted at least {$lead} ahead. The earliest date is {$earliest->toFormattedDateString()}.");
        }
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

    /** 1 hour of event = 1 CCA; the club may still edit it on the proposal. */
    public function defaultCca(CarbonInterface $startsAt, CarbonInterface $endsAt): float
    {
        return round($startsAt->diffInMinutes($endsAt, absolute: true) / 60, 1);
    }
}
