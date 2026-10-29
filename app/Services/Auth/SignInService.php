<?php

namespace App\Services\Auth;

use App\Enums\GlobalRole;
use App\Exceptions\NotInRecordsException;
use App\Models\Staff;
use App\Models\User;
use App\Services\Camsys\CamsysSync;
use App\Services\Roles\RoleService;

class SignInService
{
    public function __construct(
        private readonly CamsysSync $camsys,
        private readonly RoleService $roles,
    ) {}

    /**
     * Resolves a verified Google identity to a portal user, refreshing the student from camsys first.
     * Only camsys students, registered staff and configured super admins get in.
     *
     * @throws NotInRecordsException
     */
    public function signIn(string $email, string $googleName, ?string $avatarUrl, ?string $googleId, bool $enforceDomain = true): User
    {
        $email = strtolower(trim($email));
        if ($enforceDomain && ! str_ends_with($email, '@'.config('services.google.hosted_domain'))) {
            throw new NotInRecordsException;
        }

        $student = $this->camsys->syncByEmail($email);
        $staff = Staff::where('email', $email)->where('active', true)->first();
        $isSuperAdmin = in_array($email, array_map('strtolower', config('sld.superadmin_emails')), true);
        if (! $student && ! $staff && ! $isSuperAdmin) {
            throw new NotInRecordsException;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            // camsys/staff names are the official ones printed on SLD forms.
            'name' => $student->name ?? $staff->name ?? $googleName,
            'google_id' => $googleId ?? $user->google_id,
            'avatar_url' => $avatarUrl ?? $user->avatar_url,
            'last_login_at' => now(),
        ])->save();

        $student?->update(['user_id' => $user->id]);
        $staff?->update(['user_id' => $user->id]);

        if ($student) {
            $this->roles->grantGlobal($user, GlobalRole::Student);
        }
        if ($staff) {
            $this->roles->grantGlobal($user, GlobalRole::Staff);
        }
        if ($isSuperAdmin) {
            $this->roles->grantGlobal($user, GlobalRole::SuperAdmin);
        }

        return $user;
    }

    /**
     * Alumni keep access through the personal email they linked before graduating (CLAUDE.md §6 profile).
     * Only accounts that linked that exact address get in; nothing new is created.
     *
     * @throws NotInRecordsException
     */
    public function signInWithPersonalEmail(string $email): User
    {
        $user = User::whereRaw('lower(personal_email) = ?', [strtolower(trim($email))])->first() ?? throw new NotInRecordsException;
        if ($user->student) {
            $this->camsys->syncByEmail($user->email);
        }
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }
}
