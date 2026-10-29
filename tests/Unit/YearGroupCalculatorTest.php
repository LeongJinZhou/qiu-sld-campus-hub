<?php

use App\Services\Time\YearGroupCalculator;
use Carbon\CarbonImmutable;

$calc = fn () => new YearGroupCalculator;

it('matches the CLAUDE.md example: intake 2024-04 on 2026-10-29 is Year 3', function () use ($calc) {
    expect($calc()->calculate(2024, 4, CarbonImmutable::parse('2026-10-29'))->label)->toBe('Year 3');
});

it('moves up on the intake anniversary month', function (string $today, string $expected) use ($calc) {
    expect($calc()->calculate(2024, 4, CarbonImmutable::parse($today))->label)->toBe($expected);
})->with([
    ['2024-04-01', 'Year 1'],
    ['2025-03-31', 'Year 1'],
    ['2025-04-01', 'Year 2'],
    ['2026-04-15', 'Year 3'],
]);

it('is never below Year 1 before the intake starts', function () use ($calc) {
    expect($calc()->calculate(2027, 1, CarbonImmutable::parse('2026-10-29'))->label)->toBe('Year 1');
});

it('caps at course duration, rounding partial years up', function () use ($calc) {
    $today = CarbonImmutable::parse('2030-01-01');

    expect($calc()->calculate(2024, 1, $today, '3 Years')->label)->toBe('Year 3')
        ->and($calc()->calculate(2024, 1, $today, '1.5 Years')->label)->toBe('Year 2');
});

it('returns Foundation for foundation programmes and Alumni for graduates', function () use ($calc) {
    $today = CarbonImmutable::parse('2026-10-29');

    expect($calc()->calculate(2026, 1, $today, programmeType: 'Foundation')->label)->toBe('Foundation')
        ->and($calc()->calculate(2020, 1, $today, isAlumni: true)->label)->toBe('Alumni')
        ->and($calc()->calculate(2024, 4, $today)->short)->toBe('Y3');
});
