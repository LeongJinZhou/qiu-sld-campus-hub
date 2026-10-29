<?php

namespace App\Services\Membership;

use App\Enums\MembershipStatus;
use App\Enums\StudentStatus;
use App\Models\Membership;
use App\Notifications\DocumentNotification;
use Carbon\CarbonImmutable;

/**
 * Daily (00:10): active → grace on the expiry date, reminders on grace days 0/3/6, grace → terminated after
 * 7 days, and anyone who graduated in camsys → alumni (stays in the club, no fee).
 */
class MembershipLifecycle
{
    public const GRACE_DAYS = 7;

    public const REMINDER_DAYS = [0, 3, 6];

    /** @return array{grace: int, terminated: int, alumni: int, reminded: int} */
    public function run(CarbonImmutable $today): array
    {
        $counts = ['grace' => 0, 'terminated' => 0, 'alumni' => 0, 'reminded' => 0];

        Membership::with('user.student', 'club')
            ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace, MembershipStatus::Pending])
            ->chunkById(500, function ($memberships) use ($today, &$counts): void {
                foreach ($memberships as $m) {
                    if ($m->user->student?->status === StudentStatus::Alumni) {
                        if ($m->status !== MembershipStatus::Pending) {
                            $m->update(['status' => MembershipStatus::Alumni, 'alumni_since' => $today, 'grace_ends_on' => null]);
                            $counts['alumni']++;
                        }

                        continue;
                    }
                    if ($m->status === MembershipStatus::Active && $m->expires_on && $today->gte($m->expires_on)) {
                        $m->update(['status' => MembershipStatus::Grace, 'grace_ends_on' => $m->expires_on->copy()->addDays(self::GRACE_DAYS)]);
                        $counts['grace']++;
                    }
                    if ($m->status === MembershipStatus::Grace) {
                        if ($today->gte($m->grace_ends_on)) {
                            $m->update(['status' => MembershipStatus::Terminated]);
                            $counts['terminated']++;

                            continue;
                        }
                        $day = (int) $m->expires_on->diffInDays($today);
                        if (in_array($day, self::REMINDER_DAYS, true) && ! $m->last_reminded_at?->isSameDay($today)) {
                            $m->user->notify(new DocumentNotification(
                                "Renew your {$m->club->name} membership",
                                'Your membership expired on '.$m->expires_on->toFormattedDateString().'. Renew by '.$m->grace_ends_on->subDay()->toFormattedDateString().' to stay a member.',
                                route('membership.join', $m->club),
                            ));
                            $m->update(['last_reminded_at' => $today]);
                            $counts['reminded']++;
                        }
                    }
                }
            });

        return $counts;
    }
}
