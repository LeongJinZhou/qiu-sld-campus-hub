<?php

use App\Services\Events\ProposalRules;
use Carbon\CarbonImmutable;

$rules = fn () => new ProposalRules;

it('needs 1.5 months lead time under 60 participants and 3 months from 60', function () use ($rules) {
    $today = CarbonImmutable::parse('2026-10-29');

    expect($rules()->earliestStart(59, $today)->toDateString())->toBe('2026-12-14')
        ->and($rules()->earliestStart(60, $today)->toDateString())->toBe('2027-01-29')
        ->and($rules()->earliestStart(200, $today)->toDateString())->toBe('2027-01-29');
});

it('rejects an event that starts too soon', function () use ($rules) {
    $rules()->assertLeadTime(80, CarbonImmutable::parse('2027-01-28 09:00'), CarbonImmutable::parse('2026-10-29'));
})->throws(DomainException::class, '3 months');

it('accepts an event on the earliest allowed day', function () use ($rules) {
    $rules()->assertLeadTime(30, CarbonImmutable::parse('2026-12-14 09:00'), CarbonImmutable::parse('2026-10-29'));
    expect(true)->toBeTrue();
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
