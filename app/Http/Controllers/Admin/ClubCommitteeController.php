<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddAdvisorRequest;
use App\Http\Requests\Admin\AssignPositionRequest;
use App\Models\Club;
use App\Models\RoleAssignment;
use App\Models\Student;
use App\Models\User;
use App\Services\Roles\RoleService;
use App\Services\Time\SessionResolver;
use DomainException;
use Illuminate\Http\RedirectResponse;

class ClubCommitteeController extends Controller
{
    public function __construct(private readonly RoleService $roles, private readonly SessionResolver $sessions) {}

    public function assign(AssignPositionRequest $request, Club $club): RedirectResponse
    {
        $student = Student::where('student_number', $request->validated('student_number'))->firstOrFail();
        // Students can be appointed before their first sign-in; the account links by camsys email.
        $user = $student->user ?? User::firstOrCreate(['email' => $student->email], ['name' => $student->name]);
        $student->update(['user_id' => $user->id]);

        try {
            $this->roles->assignClubPosition($club, $this->sessions->forName($request->validated('session')), $user->load('student'), $request->validated('position'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$user->name} is now {$request->validated('position')}.");
    }

    public function addAdvisor(AddAdvisorRequest $request, Club $club): RedirectResponse
    {
        try {
            $this->roles->addAdvisor($club, $this->sessions->forName($request->validated('session')), User::findOrFail($request->validated('user_id')));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Advisor added.');
    }

    public function remove(Club $club, RoleAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->scope_id === $club->id, 404);
        $this->roles->remove($assignment);

        return back()->with('success', 'Removed.');
    }
}
