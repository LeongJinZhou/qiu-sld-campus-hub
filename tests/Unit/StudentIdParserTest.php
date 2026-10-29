<?php

use App\Services\Camsys\StudentIdParser;

it('parses a real QIU ID into intake year, month and serial', function () {
    $id = (new StudentIdParser)->parse('QIU-202404-0001');

    expect([$id->year, $id->month, $id->serial])->toBe([2024, 4, '0001']);
});

it('takes the month from the ID even when INTAKE_NUMBER disagrees', function () {
    expect((new StudentIdParser([1 => 1]))->parse('QIU-202409-0123', 2024, 1)->month)->toBe(9);
});

it('uses intake_map and YEAR_ENTRY for dummy STD IDs', function () {
    $id = (new StudentIdParser([1 => 1, 2 => 5]))->parse('STD20240003', 2023, 2);

    expect([$id->year, $id->month, $id->serial])->toBe([2023, 5, '0003']);
});

it('rejects a dummy ID whose intake has no month mapping', function () {
    (new StudentIdParser([1 => 1]))->parse('STD20240003', 2023, 7);
})->throws(InvalidArgumentException::class);

it('rejects anything else', function (string $id) {
    (new StudentIdParser([1 => 1]))->parse($id, 2024, 1);
})->with(['QIU-202413-0001', 'QIU-202400-0001', 'QIU-2024-04-0001', 'qiu-202404-0001', '20240001', ''])
    ->throws(InvalidArgumentException::class);
