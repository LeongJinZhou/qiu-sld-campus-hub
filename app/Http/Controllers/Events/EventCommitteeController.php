<?php

namespace App\Http\Controllers\Events;

use App\Enums\ScopeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\EventCommitteeRequest;
use App\Models\Event;
use App\Models\RoleAssignment;
use App\Models\Student;
use App\Services\Roles\RoleService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class EventCommitteeController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function store(EventCommitteeRequest $request, Event $event): RedirectResponse
    {
        if (! $event->proposal?->status->isEditable()) {
            return back()->with('error', 'The committee is locked while the proposal is in review.');
        }
        $user = Student::where('student_number', $request->validated('student_number'))->firstOrFail()->ensureUser();

        try {
            $this->roles->assignEventPosition($event, $user, $request->validated('position'), $request->validated('responsibility'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$user->name} added as {$request->validated('position')}.");
    }

    public function destroy(Event $event, RoleAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $event);
        abort_unless($assignment->scope_type === ScopeType::Event && $assignment->scope_id === $event->id, 404);
        if (! $event->proposal?->status->isEditable()) {
            return back()->with('error', 'The committee is locked while the proposal is in review.');
        }
        $this->roles->remove($assignment);

        return back()->with('success', 'Removed.');
    }
}
