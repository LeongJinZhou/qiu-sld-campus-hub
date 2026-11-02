<?php

use App\Services\Events\ProposalRules;
use Carbon\CarbonImmutable;

$rules = fn () => new ProposalRules;

it('needs the SLD lead time (14 days by default) before the event', function () use ($rules) {
    $today = CarbonImmutable::parse('2026-10-29');

    expect($rules()->earliestStart($today, 14)->toDateString())->toBe('2026-11-12')
        ->and($rules()->earliestStart($today, 30)->toDateString())->toBe('2026-11-28');
});

it('rejects an event that starts too soon without a reason, and accepts it with one (SLD then decides)', function () use ($rules) {
    expect(fn () => $rules()->assertLeadTime(CarbonImmutable::parse('2026-11-05 09:00'), CarbonImmutable::parse('2026-10-29'), null, 14))->toThrow(DomainException::class, '14 days');
    $rules()->assertLeadTime(CarbonImmutable::parse('2026-11-05 09:00'), CarbonImmutable::parse('2026-10-29'), 'The venue only became free this week.', 14);
    $rules()->assertLeadTime(CarbonImmutable::parse('2026-11-12 09:00'), CarbonImmutable::parse('2026-10-29'), null, 14);
    expect(true)->toBeTrue();
});

it('lists the exceptions SLD must decide: late submission and budget deficit, with reasons', function () use ($rules) {
    $data = ['starts_at' => '2026-11-05T09:00', 'late_reason' => 'Venue freed up', 'deficit_reason' => 'Club fund covers it',
        'budget' => ['revenue' => [], 'expenses' => [['price_sen' => 500, 'quantity' => 2]]]];

    expect(array_column($rules()->exceptions($data, CarbonImmutable::parse('2026-10-29'), 14), 'kind'))->toBe(['late', 'deficit'])
        ->and($rules()->exceptions([...$data, 'starts_at' => '2026-12-01T09:00', 'budget' => ['revenue' => [], 'expenses' => []]], CarbonImmutable::parse('2026-10-29'), 14))->toBe([]);
});

it('totals the budget in sen and flags when revenue does not cover expenses', function () use ($rules) {
    $totals = $rules()->budgetTotals([
        'revenue' => [['price_sen' => 1000, 'quantity' => 100], ['price_sen' => 45000, 'quantity' => 1]],
        'expenses' => [['price_sen' => 10000, 'quantity' => 1], ['price_sen' => 5000, 'quantity' => 3], ['price_sen' => 1000, 'quantity' => 120]],
    ]);

    expect($totals)->toBe(['revenue_sen' => 145000, 'expenses_sen' => 145000, 'profit_sen' => 0, 'covers_expenses' => true])
        ->and($rules()->budgetTotals(['revenue' => [], 'expenses' => [['price_sen' => 100, 'quantity' => 1]]])['covers_expenses'])->toBeFalse();
});

it('defaults CCA to event hours, 1 hour = 1 CCA', function () use ($rules) {
    expect($rules()->defaultCca(CarbonImmutable::parse('2027-01-01 08:00'), CarbonImmutable::parse('2027-01-01 12:30')))->toBe(4.5);
});
