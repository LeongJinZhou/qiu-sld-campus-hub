<?php

namespace App\Services\Membership;

use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Enums\ScopedRole;
use App\Models\Club;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\Time\SessionResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Club committee members pay the club fee like every member. Until they do, a pop-up greets them at every
 * sign-in and the reminder stays under Action needed. They have 7 days from the first reminder.
 */
class CommitteeFees
{
    public const DAYS = 7;

    public function __construct(private readonly SessionResolver $sessions, private readonly MembershipService $memberships) {}

    /**
     * Clubs whose fee this committee member still owes for the current session (none while a payment is being checked).
     *
     * @return list<array{club: array{slug: string, name: string, logo_url: ?string}, position: ?string, fee_sen: int, due_on: string, days_left: int, overdue: bool, url: string}>
     */
    public function unpaid(User $user): array
    {
        if (! $user->student?->isActive()) {
            return [];
        }
        $session = $this->sessions->current();
        $assignments = RoleAssignment::query()->acting($session)->where('user_id', $user->id)->where('role', ScopedRole::ClubCommittee)->get();
        $out = [];
        foreach ($assignments as $assignment) {
            $club = Club::find($assignment->scope_id);
            $fee = $club ? $this->memberships->fee($club) : null;
            if (! $club || ! $fee || $fee->amount_sen <= 0 || $this->paidUp($user, $club)) {
                continue;
            }
            DB::table('committee_fee_reminders')->insertOrIgnore(['user_id' => $user->id, 'club_id' => $club->id, 'session_id' => $session->id, 'first_shown_at' => now()]);
            $first = DB::table('committee_fee_reminders')->where(['user_id' => $user->id, 'club_id' => $club->id, 'session_id' => $session->id])->value('first_shown_at');
            $due = Carbon::parse($first)->addDays(self::DAYS)->endOfDay();
            $out[] = [
                'club' => ['slug' => $club->slug, 'name' => $club->name, 'logo_url' => $club->logoUrl()],
                'position' => $assignment->position,
                'fee_sen' => $fee->amount_sen,
                'due_on' => $due->toDateString(),
                'days_left' => max(0, (int) today()->diffInDays($due->copy()->startOfDay(), false)),
                'overdue' => $due->isPast(),
                'url' => route('membership.join', $club),
            ];
        }

        return $out;
    }

    /**
     * A club's current committee and whether each has paid the club fee (advisors are staff and never pay).
     *
     * @return list<array{name: string, student_number: ?string, position: ?string, status: string}>
     */
    public function forClub(Club $club): array
    {
        $fee = $this->memberships->fee($club);

        return RoleAssignment::query()->actingFor($club->id, $this->sessions->current())->where('role', ScopedRole::ClubCommittee)->with('user.student')->get()
            ->map(function (RoleAssignment $a) use ($club, $fee): array {
                $membership = Membership::where('club_id', $club->id)->where('user_id', $a->user_id)->first();
                $checking = $membership && MembershipPayment::where('membership_id', $membership->id)
                    ->whereIn('status', [PaymentStatus::Reading, PaymentStatus::PendingReview, PaymentStatus::CashPending])->exists();
                $status = ! $fee || $fee->amount_sen <= 0 ? 'no_fee'
                    : (in_array($membership?->status, [MembershipStatus::Active, MembershipStatus::Grace], true) ? 'paid' : ($checking ? 'checking' : 'unpaid'));

                return ['name' => $a->user->name, 'student_number' => $a->user->student?->student_number, 'position' => $a->position, 'status' => $status];
            })->sortBy(fn ($r) => [$r['status'] === 'unpaid' ? 0 : 1, $r['position']])->values()->all();
    }

    /** Active or renewing member, or a payment already sent and being checked. */
    private function paidUp(User $user, Club $club): bool
    {
        $membership = Membership::where('club_id', $club->id)->where('user_id', $user->id)->first();
        if (! $membership) {
            return false;
        }
        if (in_array($membership->status, [MembershipStatus::Active, MembershipStatus::Grace], true)) {
            return true;
        }

        return MembershipPayment::where('membership_id', $membership->id)
            ->whereIn('status', [PaymentStatus::Reading, PaymentStatus::PendingReview, PaymentStatus::CashPending])->exists();
    }
}
