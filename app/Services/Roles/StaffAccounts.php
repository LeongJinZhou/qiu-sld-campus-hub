<?php

namespace App\Services\Roles;

use App\Enums\GlobalRole;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/** SLD keys staff in by @qiu.edu.my email so they can be advisors/approvers before their first sign-in. */
class StaffAccounts
{
    public function __construct(private readonly RoleService $roles, private readonly AuditLogger $audit) {}

    /** @param array{email: string, name: string, title?: string|null, approver_role?: string|null} $data */
    public function create(array $data): Staff
    {
        return DB::transaction(function () use ($data): Staff {
            $email = strtolower($data['email']);
            $user = User::firstOrCreate(['email' => $email], ['name' => $data['name']]);
            $this->roles->grantGlobal($user, GlobalRole::Staff);
            $staff = Staff::create([...$data, 'email' => $email, 'user_id' => $user->id]);
            $this->audit->log('staff.created', $staff, ['email' => $email]);

            return $staff;
        });
    }

    /** @param array{name: string, title?: string|null, approver_role?: string|null, active: bool} $data */
    public function update(Staff $staff, array $data): void
    {
        $staff->update($data);
        if ($staff->user) {
            $data['active']
                ? $this->roles->grantGlobal($staff->user, GlobalRole::Staff)
                : $this->roles->revokeGlobal($staff->user, GlobalRole::Staff);
        }
        $this->audit->log('staff.updated', $staff, $staff->getChanges());
    }
}
