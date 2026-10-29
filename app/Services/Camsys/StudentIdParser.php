<?php

namespace App\Services\Camsys;

use InvalidArgumentException;

/**
 * Real IDs carry the intake: QIU-YYYYMM-NNNN. The dummy dump uses STD########, whose month
 * comes from intake_map (INTAKE_NUMBER is a sequence, not a month) and year from YEAR_ENTRY.
 */
class StudentIdParser
{
    public const REAL_PATTERN = '/^QIU-(\d{4})(0[1-9]|1[0-2])-(\d{4})$/';

    public const DUMMY_PATTERN = '/^STD(\d{4})(\d{4})$/';

    /** @param array<int, int> $intakeMonths intake_number => month */
    public function __construct(private readonly array $intakeMonths = []) {}

    public function parse(string $studentNumber, ?int $yearEntry = null, ?int $intakeNumber = null): ParsedStudentId
    {
        if (preg_match(self::REAL_PATTERN, $studentNumber, $m)) {
            return new ParsedStudentId((int) $m[1], (int) $m[2], $m[3]);
        }

        if (preg_match(self::DUMMY_PATTERN, $studentNumber, $m)) {
            $month = $intakeNumber !== null ? ($this->intakeMonths[$intakeNumber] ?? null) : null;
            if ($month === null) {
                throw new InvalidArgumentException("No intake_map month for intake {$intakeNumber} ({$studentNumber}).");
            }

            return new ParsedStudentId($yearEntry ?? (int) $m[1], $month, $m[2]);
        }

        throw new InvalidArgumentException("Unrecognised student ID: {$studentNumber}");
    }
}
