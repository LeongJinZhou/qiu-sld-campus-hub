<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Saved once, pre-filled on the next indemnity. */
#[Fillable(['user_id', 'name', 'mobile_phone', 'work_phone', 'relationship', 'alternate_name', 'alternate_phone'])]
class EmergencyContact extends Model
{
    protected function casts(): array
    {
        return ['mobile_phone' => 'encrypted', 'work_phone' => 'encrypted', 'alternate_phone' => 'encrypted'];
    }
}
