<?php

use App\Enums\StudentStatus;
use App\Services\Camsys\StatusMapper;

it('maps the default camsys statuses', function (string $status, StudentStatus $expected) {
    expect((new StatusMapper(StatusMapper::DEFAULTS))->map($status))->toBe($expected);
})->with([
    ['Enrolled', StudentStatus::Active],
    ['Active', StudentStatus::Active],
    ['Deferred', StudentStatus::Active],
    ['Suspended', StudentStatus::Active],
    ['Graduated', StudentStatus::Alumni],
    ['Completed', StudentStatus::Alumni],
    ['Withdrawn', StudentStatus::Alumni],
    ['Terminated', StudentStatus::Alumni],
    [' enrolled ', StudentStatus::Active],
    ['Something new', StudentStatus::Unknown],
]);

it('follows an admin-edited mapping', function () {
    $mapper = new StatusMapper(['active' => ['Probation'], 'alumni' => []]);

    expect($mapper->map('Probation'))->toBe(StudentStatus::Active)
        ->and($mapper->map('Enrolled'))->toBe(StudentStatus::Unknown);
});
