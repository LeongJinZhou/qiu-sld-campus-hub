<?php

namespace App\Models;

use App\Enums\ApproverRole;
use App\Enums\GlobalRole;
use App\Services\Roles\RoleService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'email', 'staff_number', 'name', 'title', 'department', 'approver_role', 'active'])]
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

    /** The staff member's sign-in account, created the first time they are given a role (as for students). */
    public function ensureUser(): User
    {
        $user = $this->user ?? User::firstOrCreate(['email' => $this->email], ['name' => $this->name]);
        if ($this->user_id !== $user->id) {
            $this->update(['user_id' => $user->id]);
        }
        if (! $user->hasRole(GlobalRole::Staff->value)) {
            app(RoleService::class)->grantGlobal($user, GlobalRole::Staff);
        }

        return $user;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
