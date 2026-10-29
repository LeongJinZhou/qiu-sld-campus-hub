<?php

namespace App\Services\Camsys;

use App\Enums\StudentStatus;
use App\Models\CamsysSyncRun;
use App\Models\IntakeMap;
use App\Models\Student;
use App\Models\User;
use App\Services\Time\YearGroupCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Pulls students from camsys (SELECT only) into `students`, copying only the columns CLAUDE.md §3 allows.
 * A student can have several rows (e.g. Foundation then Degree); the latest intake wins.
 */
class CamsysSync
{
    /** The only camsys columns the portal may read. */
    public const ALLOWED_COLUMNS = [
        'STUDENT_NUMBER', 'STUDENT_NAME', 'GENDER', 'STUDENT_MOBILE_NO', 'E_MAIL', 'PROGRAMME_STATUS',
        'PROFILE_STATUS', 'PROGRAMME', 'PROGRAMME_CODE', 'TYPE_OF_PROGRAMME', 'YEAR_ENTRY', 'INTAKE_NUMBER',
        'COURSE_DURATION', 'GRADUATED_DATE',
    ];

    public function __construct(
        private readonly StatusMapper $statuses,
        private readonly YearGroupCalculator $yearGroups,
    ) {}

    public function syncAll(string $trigger, ?User $actor = null): CamsysSyncRun
    {
        $run = CamsysSyncRun::create(['trigger' => $trigger, 'triggered_by' => $actor?->id, 'started_at' => now()]);
        $parser = new StudentIdParser(IntakeMap::months());

        try {
            $this->query()->orderBy('STUDENT_NUMBER')->chunk(500, function ($rows) use ($run, $parser): void {
                foreach ($this->latestPerStudent($rows->all()) as $row) {
                    $run->records_seen++;
                    $run->upserted += $this->upsert($row, $parser) ? 1 : 0;
                }
            });
        } catch (Throwable $e) {
            $run->error = $e->getMessage();
            report($e);
        }

        $run->finished_at = now();
        $run->save();

        return $run;
    }

    /** Refreshes one student by their QIU email (used on every login). */
    public function syncByEmail(string $email): ?Student
    {
        $rows = $this->query()->whereRaw('LOWER(E_MAIL) = ?', [strtolower($email)])->get()->all();
        $row = $this->latestPerStudent($rows)[0] ?? null;

        return $row ? $this->upsert($row, new StudentIdParser(IntakeMap::months())) : null;
    }

    /** Recomputes year groups from stored data; the date moves even when camsys does not. */
    public function recomputeYearGroups(): int
    {
        $today = CarbonImmutable::now();
        $updated = 0;
        Student::query()->whereNotNull('intake_month')->chunkById(500, function ($students) use ($today, &$updated): void {
            foreach ($students as $student) {
                $student->year_group = $this->yearGroups->calculate(
                    $student->year_entry,
                    $student->intake_month,
                    $today,
                    $student->course_duration,
                    $student->type_of_programme,
                    $student->status === StudentStatus::Alumni,
                )->label;
                $updated += (int) $student->isDirty('year_group');
                $student->save();
            }
        });

        return $updated;
    }

    private function query(): Builder
    {
        return DB::connection('camsys')->table('qapi_registration_mdl')->select(self::ALLOWED_COLUMNS);
    }

    /**
     * @param  list<object>  $rows
     * @return list<object>
     */
    private function latestPerStudent(array $rows): array
    {
        $latest = [];
        foreach ($rows as $row) {
            $current = $latest[$row->STUDENT_NUMBER] ?? null;
            if (! $current || [$row->YEAR_ENTRY, $row->INTAKE_NUMBER] > [$current->YEAR_ENTRY, $current->INTAKE_NUMBER]) {
                $latest[$row->STUDENT_NUMBER] = $row;
            }
        }

        return array_values($latest);
    }

    private function upsert(object $row, StudentIdParser $parser): ?Student
    {
        try {
            $intake = $parser->parse((string) $row->STUDENT_NUMBER, (int) $row->YEAR_ENTRY, $row->INTAKE_NUMBER !== null ? (int) $row->INTAKE_NUMBER : null);
        } catch (InvalidArgumentException $e) {
            Log::warning('camsys sync skipped student', ['reason' => $e->getMessage()]);

            return null;
        }

        $status = $this->statuses->map($row->PROGRAMME_STATUS);
        $email = strtolower(trim((string) $row->E_MAIL));

        return Student::updateOrCreate(['student_number' => $row->STUDENT_NUMBER], [
            'user_id' => User::where('email', $email)->value('id'),
            'name' => $row->STUDENT_NAME,
            'email' => $email,
            'gender' => $row->GENDER,
            'mobile_no' => $row->STUDENT_MOBILE_NO,
            'programme' => $row->PROGRAMME,
            'programme_code' => $row->PROGRAMME_CODE,
            'type_of_programme' => $row->TYPE_OF_PROGRAMME,
            'programme_status' => $row->PROGRAMME_STATUS,
            'profile_status' => $row->PROFILE_STATUS,
            'year_entry' => $intake->year,
            'intake_number' => $row->INTAKE_NUMBER,
            'intake_month' => $intake->month,
            'course_duration' => $row->COURSE_DURATION,
            'expected_end_date' => $this->date($row->GRADUATED_DATE),
            'status' => $status,
            'year_group' => $this->yearGroups->calculate(
                $intake->year,
                $intake->month,
                CarbonImmutable::now(),
                $row->COURSE_DURATION,
                $row->TYPE_OF_PROGRAMME,
                $status === StudentStatus::Alumni,
            )->label,
            'synced_at' => now(),
        ]);
    }

    /** camsys stores dates as varchar. */
    private function date(?string $value): ?string
    {
        try {
            return $value ? CarbonImmutable::parse($value)->toDateString() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
