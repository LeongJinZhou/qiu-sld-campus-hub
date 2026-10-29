<?php

namespace App\Services\Time;

use App\Models\AcademicSession;
use Carbon\CarbonImmutable;

/** Academic session runs 1 July – 30 June, labelled "2026/2027" (CLAUDE.md §4). */
class SessionResolver
{
    public const START_MONTH = 7;

    public function nameFor(CarbonImmutable $date): string
    {
        $startYear = $date->month >= self::START_MONTH ? $date->year : $date->year - 1;

        return $startYear.'/'.($startYear + 1);
    }

    public function current(): AcademicSession
    {
        return $this->forName($this->nameFor(CarbonImmutable::now()));
    }

    /** Finds or creates the session row for a "YYYY/YYYY" name. */
    public function forName(string $name): AcademicSession
    {
        $startYear = (int) substr($name, 0, 4);

        return AcademicSession::firstOrCreate(['name' => $name], [
            'starts_on' => CarbonImmutable::create($startYear, self::START_MONTH, 1),
            'ends_on' => CarbonImmutable::create($startYear + 1, self::START_MONTH - 1, 30),
        ]);
    }
}
