<?php

namespace App\Enums;

/** Account-wide roles, stored with spatie/laravel-permission under the system team (id 0). */
enum GlobalRole: string
{
    case Student = 'student';
    case Staff = 'staff';
    case Admin = 'admin';
    case SuperAdmin = 'superadmin';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'SLD Officer',
            self::SuperAdmin => 'Super Admin',
            default => ucfirst($this->value),
        };
    }
}
