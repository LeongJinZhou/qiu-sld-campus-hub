<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Grace = 'grace';
    case Terminated = 'terminated';
    case Alumni = 'alumni';

    /** Counts as a member for lists, AGM attendance and voting rules. */
    public function isMember(): bool
    {
        return in_array($this, [self::Active, self::Grace, self::Alumni], true);
    }
}
