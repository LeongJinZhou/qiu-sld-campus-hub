<?php

namespace App\Services\Camsys;

use App\Enums\StudentStatus;
use App\Models\Setting;

/** Maps camsys PROGRAMME_STATUS to active/alumni using the admin-editable `status_mapping` setting. */
class StatusMapper
{
    public const SETTING_KEY = 'status_mapping';

    public const DEFAULTS = [
        'active' => ['Enrolled', 'Active', 'Deferred', 'Suspended'],
        'alumni' => ['Graduated', 'Completed', 'Withdrawn', 'Terminated'],
    ];

    /** @var array{active: list<string>, alumni: list<string>} */
    private array $mapping;

    /** @param array{active: list<string>, alumni: list<string>}|null $mapping */
    public function __construct(?array $mapping = null)
    {
        $this->mapping = $mapping ?? Setting::read(self::SETTING_KEY, self::DEFAULTS);
    }

    public function map(?string $programmeStatus): StudentStatus
    {
        $status = strtolower(trim((string) $programmeStatus));

        return match (true) {
            in_array($status, array_map('strtolower', $this->mapping['active']), true) => StudentStatus::Active,
            in_array($status, array_map('strtolower', $this->mapping['alumni']), true) => StudentStatus::Alumni,
            default => StudentStatus::Unknown,
        };
    }
}
