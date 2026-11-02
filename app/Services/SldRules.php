<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Rules SLD can change in Settings, with the agreed defaults:
 *  - proposal_lead_days: proposals are submitted at least this many days before the event (exceptions need a reason
 *    and SLD's approval)
 *  - min_photos: photos required for a weekly activity report, an AGM and a completed event
 *  - transfer_days: Finance transfers approved withdrawal money within this many days (overdue is flagged to SLD)
 *  - handover_days: how long the outgoing committee overlaps with the incoming one after SLD starts the handover
 */
class SldRules
{
    public const KEY = 'sld_rules';

    public const DEFAULTS = ['proposal_lead_days' => 14, 'min_photos' => 3, 'transfer_days' => 14, 'handover_days' => 7];

    public const LIMITS = ['proposal_lead_days' => [0, 180], 'min_photos' => [0, 20], 'transfer_days' => [1, 90], 'handover_days' => [0, 60]];

    /** @return array<string, int> */
    public function all(): array
    {
        return array_map('intval', array_replace(self::DEFAULTS, array_intersect_key(Setting::read(self::KEY), self::DEFAULTS)));
    }

    public function get(string $key): int
    {
        return $this->all()[$key];
    }

    /** @param array<string, int> $values */
    public function save(array $values): void
    {
        Setting::write(self::KEY, array_replace($this->all(), array_intersect_key(array_map('intval', $values), self::DEFAULTS)));
    }
}
