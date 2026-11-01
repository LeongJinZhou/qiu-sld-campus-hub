<?php

use App\Support\SearchTokens;

it('turns typed dates and times into the compact form used in transaction IDs', function () {
    expect(SearchTokens::from('2026-11-01 14:30 jinzhou'))->toBe(['20261101', '1430', 'JINZHOU'])
        ->and(SearchTokens::from('1/11/2026 2:30pm'))->toBe(['20261101', '1430'])
        ->and(SearchTokens::from('GPS_BEACH-CLEANUP'))->toBe(['GPS', 'BEACHCLEANUP']);
});

it('needs every word to match, in any order', function () {
    $id = 'GPS-CLUB_BEACH-CLEANUP_STD20240010_JINZHOU-LEONG_20261101_1430_MBB123';

    expect(SearchTokens::matches('leong 1/11/2026', $id))->toBeTrue()
        ->and(SearchTokens::matches('beach cleanup 14:30', $id))->toBeTrue()
        ->and(SearchTokens::matches('leong 2/11/2026', $id))->toBeFalse();
});
