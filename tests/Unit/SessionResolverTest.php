<?php

use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;

it('rolls over on 1 July', function (string $date, string $session) {
    expect((new SessionResolver)->nameFor(CarbonImmutable::parse($date)))->toBe($session);
})->with([
    ['2027-06-30', '2026/2027'],
    ['2027-07-01', '2027/2028'],
    ['2026-10-29', '2026/2027'],
    ['2027-01-01', '2026/2027'],
]);
