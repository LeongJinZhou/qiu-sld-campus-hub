<?php

namespace App\Services\Auth;

use App\Enums\GlobalRole;
use App\Enums\StudentStatus;
use App\Exceptions\NotInRecordsException;
use App\Http\Controllers\PreferencesController;
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
        $returning = $user->last_login_at !== null;
        $user->fill([
            // camsys/staff names are the official ones printed on SLD forms.
            'name' => $student->name ?? $staff->name ?? $googleName,
            'google_id' => $googleId ?? $user->google_id,
            'avatar_url' => $avatarUrl ?? $user->avatar_url,
            'last_login_at' => now(),
        ])->save();
        if ($returning) {
            $this->retireTours($user);
        }

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
     * Alumni keep access through the personal email they linked before graduating.
     * Only accounts that linked that exact address get in; nothing new is created.
     *
     * @throws NotInRecordsException
     */
    public function signInWithPersonalEmail(string $email): User
    {
        // Only graduates use this door: never staff, SLD or super admins (they sign in with their QIU Google account),
        // and only when exactly one account has linked this address.
        $matches = User::with('student', 'staff')->whereRaw('lower(personal_email) = ?', [strtolower(trim($email))])->get();
        $user = $matches->count() === 1 ? $matches->first() : throw new NotInRecordsException;
        if ($user->staff || $user->hasAnyRole(['staff', 'admin', 'superadmin']) || in_array(strtolower($user->email), array_map('strtolower', config('sld.superadmin_emails')), true)
            || $user->student?->status !== StudentStatus::Alumni) {
            throw new NotInRecordsException;
        }
        if ($user->student) {
            $this->camsys->syncByEmail($user->email);
        }
        $user->forceFill(['last_login_at' => now()])->save();
        $this->retireTours($user);

        return $user;
    }

    /** The student and committee guides open by themselves during the first sign-in only; Profile can replay them. */
    private function retireTours(User $user): void
    {
        $seen = array_values(array_unique([...($user->tours_seen ?? []), ...PreferencesController::TOURS]));
        $user->forceFill(['tours_seen' => $seen])->save();
    }
}
