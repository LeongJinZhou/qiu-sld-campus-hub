<?php

namespace App\Enums;

enum ScopedRole: string
{
    case ClubCommittee = 'club_committee';
    case EventCommittee = 'event_committee';
    case Advisor = 'advisor';
}
