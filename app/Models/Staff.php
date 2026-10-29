<?php

namespace App\Models;

use App\Enums\ApproverRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'email', 'name', 'title', 'approver_role', 'active'])]
class Staff extends Model
{
    protected $table = 'staff';

    protected function casts(): array
    {
        return [
            'approver_role' => ApproverRole::class,
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
