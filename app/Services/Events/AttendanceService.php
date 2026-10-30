<?php

namespace App\Services\Events;

use App\Enums\ScopeType;
use App\Models\Attendance;
use App\Models\CcaRecord;
use App\Models\Event;
use App\Models\RoleAssignment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Clock-in/out for approved events. CCA is recorded on clock-out (1 hour = 1 CCA, as approved). */
class AttendanceService
{
    /** Attendance opens an hour before the start and closes two hours after the end. */
    public const OPENS_BEFORE_MINUTES = 60;

    public const CLOSES_AFTER_MINUTES = 120;

    public function clock(Event $event, User $user, string $action, string $method, ?User $addedBy = null): Attendance
    {
        $this->assertOpen($event);
        if (! $user->student?->isActive()) {
            throw new DomainException("{$user->name} is not an active student.");
        }
        $role = $this->roleOf($event, $user);
        if ($event->is_outdoor && ! $event->indemnities()->where('user_id', $user->id)->exists()) {
            throw new DomainException("{$user->name} has not signed the indemnity form, so clock-in is blocked.");
        }

        return DB::transaction(function () use ($event, $user, $action, $method, $addedBy, $role): Attendance {
            $attendance = Attendance::firstOrNew(['event_id' => $event->id, 'user_id' => $user->id]);
            $attendance->fill(['role' => $role, 'method' => $method, 'added_by' => $addedBy?->id ?? $attendance->added_by]);

            if ($action === 'in') {
                $attendance->clock_in_at ??= now();
            } else {
                if (! $attendance->clock_in_at) {
                    throw new DomainException("{$user->name} has not clocked in yet.");
                }
                $attendance->clock_out_at = now();
                $this->recordCca($event, $user, $role);
            }
            $attendance->save();

            return $attendance;
        });
    }

    /** Committee adds someone by hand: both clock-in and clock-out, CCA recorded. */
    public function addManually(Event $event, User $user, User $committee): Attendance
    {
        $this->clock($event, $user, 'in', 'manual', $committee);

        return $this->clock($event, $user, 'out', 'manual', $committee);
    }

    public function roleOf(Event $event, User $user): string
    {
        return RoleAssignment::where('scope_type', ScopeType::Event)->where('scope_id', $event->id)->where('user_id', $user->id)->exists()
            ? 'committee'
            : 'participant';
    }

    private function assertOpen(Event $event): void
    {
        if (! $event->attendance_open) {
            throw new DomainException('Attendance opens once the proposal is approved.');
        }
        $opens = $event->starts_at->copy()->subMinutes(self::OPENS_BEFORE_MINUTES);
        $closes = $event->ends_at->copy()->addMinutes(self::CLOSES_AFTER_MINUTES);
        if (now()->lt($opens) || now()->gt($closes)) {
            throw new DomainException("Attendance is open from {$opens->format('j M, g:i A')} to {$closes->format('j M, g:i A')}.");
        }
    }

    private function recordCca(Event $event, User $user, string $role): void
    {
        CcaRecord::updateOrCreate(['user_id' => $user->id, 'event_id' => $event->id], [
            'session_id' => $event->session_id,
            'role' => $role,
            'hours' => $role === 'committee' ? $event->cca_committee : $event->cca_participant,
        ]);
    }
}
