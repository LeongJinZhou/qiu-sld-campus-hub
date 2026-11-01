<?php

namespace App\Models;

use App\Enums\ScopedRole;
use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'role', 'scope_type', 'scope_id', 'session_id', 'position', 'responsibility', 'is_compulsory', 'starts_on', 'ends_on'])]
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
     * Club roles that count for a session today: that session's assignments that have not ended (an outgoing
     * committee ends 7 days after an election), plus the next session's elected committee once its handover day
     * has come.
     *
     * @param  Builder<RoleAssignment>  $query
     */
    public function scopeActingFor(Builder $query, int $clubId, AcademicSession $session): void
    {
        $next = AcademicSession::where('starts_on', '>', $session->starts_on)->orderBy('starts_on')->value('id');
        $query->where('scope_type', ScopeType::Club)->where('scope_id', $clubId)->where(function (Builder $q) use ($session, $next): void {
            $q->where(fn (Builder $w) => $w->where('session_id', $session->id)->where(fn (Builder $e) => $e->whereNull('ends_on')->orWhereDate('ends_on', '>=', today())))
                ->when($next, fn (Builder $w) => $w->orWhere(fn (Builder $n) => $n->where('session_id', $next)->whereDate('starts_on', '<=', today())));
        });
    }
}
