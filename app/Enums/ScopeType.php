<?php

namespace App\Enums;

enum ScopeType: string
{
    case System = 'system';
    case Club = 'club';
    case Event = 'event';
}
