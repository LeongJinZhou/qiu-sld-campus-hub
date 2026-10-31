<?php

namespace App\Http\Controllers;

use App\Enums\MembershipStatus;
use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use App\Models\Club;
use App\Models\Membership;
use App\Models\RoleAssignment;
use App\Services\Time\SessionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "My clubs": the clubs I serve (with management shortcuts) and the clubs I'm a member of, without searching. */
class MyClubsController extends Controller
{
    public function __invoke(Request $request, SessionResolver $sessions): Response
    {
        $user = $request->user();
        $roles = RoleAssignment::where('user_id', $user->id)->where('scope_type', ScopeType::Club)
            ->where('session_id', $sessions->current()->id)->get()->keyBy('scope_id');
        $memberships = Membership::where('user_id', $user->id)
            ->whereIn('status', [MembershipStatus::Active, MembershipStatus::Grace, MembershipStatus::Pending, MembershipStatus::Alumni])->get()->keyBy('club_id');
        $clubs = Club::with('category')->whereIn('id', $roles->keys()->merge($memberships->keys())->unique())->orderBy('name')->get();

        return Inertia::render('MyClubs/Index', [
            'clubs' => $clubs->map(function (Club $club) use ($roles, $memberships) {
                $role = $roles[$club->id] ?? null;
                $membership = $memberships[$club->id] ?? null;

                return [
                    'slug' => $club->slug, 'name' => $club->name, 'logo_url' => $club->logoUrl(), 'category' => $club->category?->name,
                    'role' => $role ? ($role->role === ScopedRole::Advisor ? 'Advisor' : $role->position) : null,
                    'manages' => $role !== null,
                    'membership' => $membership ? [
                        'status' => $membership->status->value,
                        'expires_on' => $membership->expires_on?->format('j M Y'),
                    ] : null,
                ];
            })->sortByDesc('manages')->values(),
        ]);
    }
}
