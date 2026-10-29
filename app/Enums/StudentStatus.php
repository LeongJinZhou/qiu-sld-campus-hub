<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Alumni = 'alumni';
    case Unknown = 'unknown';
}
