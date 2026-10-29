<?php

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\Camsys\CamsysSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const SENSITIVE_COLUMNS = ['IC_PASSPORT', 'ADDRESS', 'RELIGION', 'RACE', 'CGPA', 'SCORE', 'DATE_OF_BIRTH', 'icpassport', 'ID_CARD_NUMBER', 'OVERALL_SCORE', 'PLACE_OF_BIRTH', 'POSTCODE'];

beforeEach(function () {
    seedCamsys();
    $this->travelTo('2026-10-29 10:00:00');
});

it('upserts every camsys student with status and year group', function () {
    $run = app(CamsysSync::class)->syncAll('manual');

    expect($run->error)->toBeNull()
        ->and($run->upserted)->toBe(10)
        ->and(Student::where('student_number', 'STD20240001')->value('year_group'))->toBe('Year 3')
        ->and(Student::where('student_number', 'STD20220008')->first()->status)->toBe(StudentStatus::Alumni)
        ->and(Student::where('student_number', 'STD20220008')->value('year_group'))->toBe('Alumni');
});

it('never copies sensitive camsys columns into the portal', function () {
    DB::connection('camsys')->enableQueryLog();
    app(CamsysSync::class)->syncAll('manual');

    $columns = array_map('strtolower', Schema::getColumnListing('students'));
    foreach (SENSITIVE_COLUMNS as $column) {
        expect($columns)->not->toContain(strtolower($column));
    }

    $sql = collect(DB::connection('camsys')->getQueryLog())->pluck('query')->implode("\n");
    foreach (SENSITIVE_COLUMNS as $column) {
        expect($sql)->not->toContain($column);
    }

    $stored = json_encode(Student::all()->toArray());
    expect($stored)->not->toContain('010203-08-1234')     // IC
        ->not->toContain('Jalan Sultan Azlan Shah')          // address
        ->not->toContain('Islam');                           // religion
});

it('only ever SELECTs from camsys', function () {
    DB::connection('camsys')->enableQueryLog();
    app(CamsysSync::class)->syncAll('manual');
    app(CamsysSync::class)->syncByEmail('ahmad.razak@example.com');

    $statements = collect(DB::connection('camsys')->getQueryLog())->pluck('query');
    expect($statements)->not->toBeEmpty()
        ->and($statements->every(fn (string $q) => str_starts_with(strtolower(ltrim($q)), 'select')))->toBeTrue();
});

it('refreshes a single student by email, case-insensitively', function () {
    $student = app(CamsysSync::class)->syncByEmail('Ahmad.Razak@Example.com');

    expect($student->student_number)->toBe('STD20240001')
        ->and(app(CamsysSync::class)->syncByEmail('nobody@qiu.edu.my'))->toBeNull();
});

it('recomputes year groups as the date moves', function () {
    app(CamsysSync::class)->syncAll('manual');
    $this->travelTo('2027-01-15');

    app(CamsysSync::class)->recomputeYearGroups();

    // Intake Jan 2024, 3-year course: Year 4 by formula, capped at 3.
    expect(Student::where('student_number', 'STD20240001')->value('year_group'))->toBe('Year 3')
        // Intake Jan 2023, 4-year course.
        ->and(Student::where('student_number', 'STD20230006')->value('year_group'))->toBe('Year 4');
});

it('records each run in the sync log', function () {
    app(CamsysSync::class)->syncAll('manual');

    $this->assertDatabaseHas('camsys_sync_runs', ['trigger' => 'manual', 'records_seen' => 10, 'upserted' => 10, 'error' => null]);
});
