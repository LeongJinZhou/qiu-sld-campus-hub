<?php

namespace App\Services\Camsys;

final readonly class ParsedStudentId
{
    public function __construct(public int $year, public int $month, public string $serial) {}
}
