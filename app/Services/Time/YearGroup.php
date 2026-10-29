<?php

namespace App\Services\Time;

final readonly class YearGroup
{
    private function __construct(public string $label, public string $short) {}

    public static function year(int $number): self
    {
        return new self("Year {$number}", "Y{$number}");
    }

    public static function foundation(): self
    {
        return new self('Foundation', 'Foundation');
    }

    public static function alumni(): self
    {
        return new self('Alumni', 'Alumni');
    }
}
