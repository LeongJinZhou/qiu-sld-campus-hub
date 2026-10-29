<?php

namespace App\Models;

use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'role', 'scope_type', 'scope_id', 'session_id', 'position', 'responsibility', 'is_compulsory', 'starts_on', 'ends_on'])]
class RoleAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'role' => ScopedRole::class,
            'scope_type' => ScopeType::class,
            'is_compulsory' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AcademicSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }
}
