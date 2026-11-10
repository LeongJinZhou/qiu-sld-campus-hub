<?php

namespace App\Http\Controllers\Membership;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Membership;
use App\Notifications\DocumentNotification;
use App\Services\AuditLogger;
use App\Services\Membership\MembershipLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The committee (or SLD) manages members in grace, terminated members and alumni by hand: remind someone in
 * grace, give them more days, end a membership now, mark a graduate as alumni, or undo a wrong alumni mark.
 * Every change is logged against the person who made it.
 */
class MembershipStatusController extends Controller
{
    /** Which actions apply to which current status. */
    private const ALLOWED = [
        'remind' => [MembershipStatus::Grace],
        'extend' => [MembershipStatus::Grace],
        'terminate' => [MembershipStatus::Active, MembershipStatus::Grace],
        'alumni' => [MembershipStatus::Active, MembershipStatus::Grace, MembershipStatus::Terminated],
        'restore' => [MembershipStatus::Alumni],
    ];

    public function __invoke(Request $request, Club $club, Membership $membership, MembershipLifecycle $lifecycle, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('treasure', $club);
        abort_unless($membership->club_id === $club->id, 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::ALLOWED))],
            'days' => ['nullable', 'required_if:action,extend', 'integer', 'between:1,30'],
            'reason' => ['nullable', 'required_if:action,terminate', 'string', 'max:300'],
        ], ['reason.required_if' => 'Give a reason; the member is told why.']);
        $action = $data['action'];
        if (! in_array($membership->status, self::ALLOWED[$action], true)) {
            return back()->with('error', "This action cannot be performed on a member whose status is {$membership->status->value}.");
        }
        $membership->loadMissing('user', 'club');
        $name = $membership->user->name;
        $today = CarbonImmutable::today();

        $message = match ($action) {
            'remind' => $this->remind($membership),
            'extend' => tap("{$name} has {$data['days']} more day(s) to renew.", fn () => $lifecycle->extendGrace($membership, (int) $data['days'])),
            'terminate' => tap("{$name}'s membership has ended.", fn () => $lifecycle->terminate($membership, trim($data['reason']))),
            'alumni' => tap("{$name} is now alumni.", fn () => $lifecycle->toAlumni($membership, $today)),
            'restore' => $this->restore($membership, $today),
        };
        $audit->log("membership.{$action}", $membership, array_filter(['days' => $data['days'] ?? null, 'reason' => $data['reason'] ?? null]));

        return back()->with('success', $message);
    }

    private function remind(Membership $m): string
    {
        $m->user->notify(new DocumentNotification(
            "Renew your {$m->club->name} membership",
            'Your membership expired on '.$m->expires_on?->format('j M Y').'. Renew by '.$m->grace_ends_on?->copy()->subDay()->format('j M Y').' to stay a member.',
            route('membership.join', $m->club),
        ));
        $m->update(['last_reminded_at' => now()]);

        return "Reminder sent to {$m->user->name}.";
    }

    /** Undo a wrong alumni mark: back to active while paid up, otherwise into a fresh 7-day grace. */
    private function restore(Membership $m, CarbonImmutable $today): string
    {
        $paid = $m->expires_on && $m->expires_on->gt($today);
        $m->update($paid
            ? ['status' => MembershipStatus::Active, 'alumni_since' => null]
            : ['status' => MembershipStatus::Grace, 'alumni_since' => null, 'grace_ends_on' => $today->addDays(MembershipLifecycle::GRACE_DAYS)]);

        return $paid ? "{$m->user->name} is an active member again." : "{$m->user->name} is back in grace with 7 days to renew.";
    }
}
