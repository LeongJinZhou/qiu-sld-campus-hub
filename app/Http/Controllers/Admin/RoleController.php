<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GlobalRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\User;
use App\Services\Roles\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Super Admin only: grant or revoke SLD Officer / Super Admin. Student and staff come from records. */
class RoleController extends Controller
{
    private const MANAGED = [GlobalRole::Admin, GlobalRole::SuperAdmin];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

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
                ]),
            'managedRoles' => array_map(fn (GlobalRole $r) => ['value' => $r->value, 'label' => $r->label()], self::MANAGED),
        ]);
    }

    public function update(UpdateUserRolesRequest $request, User $user, RoleService $roles): RedirectResponse
    {
        $wanted = $request->validated('roles');
        foreach (self::MANAGED as $role) {
            in_array($role->value, $wanted, true) ? $roles->grantGlobal($user, $role) : $roles->revokeGlobal($user, $role);
        }

        return back()->with('success', "Roles updated for {$user->name}.");
    }
}
