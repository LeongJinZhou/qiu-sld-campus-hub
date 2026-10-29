<?php

namespace App\Enums;

/** Cash handover state machine: each side confirms every step. */
enum HandoverStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case StudentReady = 'student_ready';
    case CommitteeReady = 'committee_ready';
    case BothReady = 'both_ready';
    case PhotoTaken = 'photo_taken';
    case Completed = 'completed';
    case Expired = 'expired';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Declined, self::Completed, self::Expired], true);
    }
}
