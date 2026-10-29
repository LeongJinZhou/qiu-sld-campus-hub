<?php

namespace App\Services\Time;

use Carbon\CarbonImmutable;

/** floor(months since intake / 12) + 1, capped at course duration. */
class YearGroupCalculator
{
    public function calculate(
        int $entryYear,
        int $intakeMonth,
        CarbonImmutable $today,
        ?string $courseDuration = null,
        ?string $programmeType = null,
        bool $isAlumni = false,
    ): YearGroup {
        if ($isAlumni) {
            return YearGroup::alumni();
        }
        if (strcasecmp((string) $programmeType, 'Foundation') === 0) {
            return YearGroup::foundation();
        }

        $months = ($today->year - $entryYear) * 12 + ($today->month - $intakeMonth);
        $year = max(1, intdiv(max(0, $months), 12) + 1);

        $cap = $this->durationInYears($courseDuration);

        return YearGroup::year($cap ? min($year, $cap) : $year);
    }

    /** "3 Years" -> 3, "1.5 Years" -> 2 (a partial final year still counts). */
    private function durationInYears(?string $courseDuration): ?int
    {
        if (! preg_match('/(\d+(?:\.\d+)?)/', (string) $courseDuration, $match)) {
            return null;
        }

        return (int) ceil((float) $match[1]);
    }
}
