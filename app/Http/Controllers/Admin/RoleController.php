<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GlobalRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\Staff;
use App\Models\User;
use App\Services\Roles\RoleService;
use App\Services\Roles\StaffAccounts;
use App\Support\QueryInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who runs the SLD portal. The Super Admin grants or revokes SLD Officer and Super Admin; an SLD Officer grants or
 * revokes SLD Officer for others (never Super Admin, and never on a Super Admin). Either can add a new SLD admin
 * by QIU email before their first sign-in. Student and staff roles come from records.
 */
class RoleController extends Controller
{
    /** @return list<GlobalRole> the roles this person may hand out */
    public static function managedBy(User $actor): array
    {
        return $actor->hasRole(GlobalRole::SuperAdmin->value) ? [GlobalRole::Admin, GlobalRole::SuperAdmin] : [GlobalRole::Admin];
    }

    public function index(Request $request): Response
    {
        $search = QueryInput::text($request, 'search', 100);

        return Inertia::render('Admin/Roles/Index', [
            'search' => $search,
            'users' => User::with('roles')
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->globalRoles(),
                    'is_me' => $u->is($request->user()),
                    // An SLD Officer cannot change a Super Admin's roles.
                    'locked' => $u->is($request->user()) || ($u->hasRole(GlobalRole::SuperAdmin->value) && ! $request->user()->hasRole(GlobalRole::SuperAdmin->value)),
                ]),
            'managedRoles' => array_map(fn (GlobalRole $r) => ['value' => $r->value, 'label' => $r->label()], self::managedBy($request->user())),
            'domain' => config('services.google.hosted_domain'),
        ]);
    }

    public function update(UpdateUserRolesRequest $request, User $user, RoleService $roles): RedirectResponse
    {
        $wanted = $request->validated('roles');
        foreach (self::managedBy($request->user()) as $role) {
            in_array($role->value, $wanted, true) ? $roles->grantGlobal($user, $role) : $roles->revokeGlobal($user, $role);
        }

        return back()->with('success', "Roles updated for {$user->name}.");
    }

    /** Add a new SLD admin by QIU email: a staff record (so they can sign in with Google) plus the SLD Officer role. */
    public function store(Request $request, StaffAccounts $accounts, RoleService $roles): RedirectResponse
    {
        $domain = (string) config('services.google.hosted_domain');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'ends_with:@'.$domain],
            'title' => ['nullable', 'string', 'max:100'],
        ], ['email.ends_with' => "Use a QIU email (@{$domain})."]);
        $email = strtolower($data['email']);
        $staff = Staff::where('email', $email)->first();
        if ($staff) {
            $staff->update(['active' => true]);
        } else {
            $staff = $accounts->create(['email' => $email, 'name' => $data['name'], 'title' => ($data['title'] ?? '') ?: 'SLD Officer', 'department' => 'Student Life Division']);
        }
        $user = $staff->ensureUser();
        $roles->grantGlobal($user, GlobalRole::Admin);

        return back()->with('success', "{$user->name} is now an SLD admin. They sign in with their QIU Google account.");
    }
}
