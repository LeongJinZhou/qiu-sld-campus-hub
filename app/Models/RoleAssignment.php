<?php

namespace App\Models;

use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'role', 'scope_type', 'scope_id', 'session_id', 'position', 'responsibility', 'is_compulsory', 'starts_on', 'ends_on', 'handover_state'])]
class RoleAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'role' => ScopedRole::class,
            'scope_type' => ScopeType::class,
            'is_compulsory' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AcademicSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    /**
     * Club roles that count for a session today, in one club or (clubId null) any club: that session's assignments
     * that have not ended, plus the next session's elected committee once SLD's handover gives them access.
     * $training also counts the incoming committee during the handover overlap (view-only "training access").
     * Disabled accounts (deactivated staff) never count.
     *
     * @param  Builder<RoleAssignment>  $query
     */
    public function scopeActing(Builder $query, AcademicSession $session, ?int $clubId = null, bool $training = false): void
    {
        $next = AcademicSession::where('starts_on', '>', $session->starts_on)->orderBy('starts_on')->value('id');
        $query->where('scope_type', ScopeType::Club)
            ->when($clubId !== null, fn (Builder $q) => $q->where('scope_id', $clubId))
            ->whereHas('user', fn (Builder $u) => $u->whereDoesntHave('staff', fn (Builder $s) => $s->where('active', false)))
            ->where(function (Builder $q) use ($session, $next, $training): void {
                $q->where(fn (Builder $w) => $w->where('session_id', $session->id)->where(fn (Builder $e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', today())))
                    ->when($next, fn (Builder $w) => $w->orWhere(fn (Builder $n) => $n->where('session_id', $next)->whereDate('starts_on', '<=', today())
                        ->where(fn (Builder $h) => $h->whereNull('handover_state')->when($training, fn (Builder $t) => $t->orWhere('handover_state', 'training')))));
            });
    }

    /** @param Builder<RoleAssignment> $query */
    public function scopeActingFor(Builder $query, int $clubId, AcademicSession $session, bool $training = false): void
    {
        $query->acting($session, $clubId, $training);
    }

    public function inTraining(): bool
    {
        return $this->handover_state === 'training';
    }
}
