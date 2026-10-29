<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Club;
use Carbon\CarbonImmutable;

/**
 * Club status (CLAUDE.md §6): Active, At-risk (no activity for 2+ months), Inactive (3+ months).
 * Activity = approved events, meetings, posts and other recorded club actions (fees, bookings, payments).
 */
class ClubStatusService
{
    public const AT_RISK_MONTHS = 2;

    public const INACTIVE_MONTHS = 3;

    public function lastActivity(Club $club): ?CarbonImmutable
    {
        $dates = array_filter([
            $club->last_activity_at,
            $club->events()->where('status', DocumentStatus::Approved)->max('ends_at'),
            $club->meetings()->where('starts_at', '<=', now())->max('starts_at'),
            $club->posts()->max('published_at'),
        ]);

        return $dates === [] ? null : CarbonImmutable::parse(max(array_map(fn ($d) => CarbonImmutable::parse($d)->getTimestamp(), $dates)));
    }

    public function statusFor(Club $club, CarbonImmutable $today): string
    {
        $last = $this->lastActivity($club) ?? CarbonImmutable::parse($club->created_at);

        return match (true) {
            $last->lte($today->subMonthsNoOverflow(self::INACTIVE_MONTHS)) => 'inactive',
            $last->lte($today->subMonthsNoOverflow(self::AT_RISK_MONTHS)) => 'at_risk',
            default => 'active',
        };
    }

    /** Daily: recompute every club; returns how many changed. */
    public function run(CarbonImmutable $today): int
    {
        $changed = 0;
        foreach (Club::all() as $club) {
            $status = $this->statusFor($club, $today);
            if ($club->status !== $status) {
                $club->update(['status' => $status]);
                $changed++;
            }
        }

        return $changed;
    }
}
