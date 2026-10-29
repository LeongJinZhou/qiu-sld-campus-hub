<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['session_id', 'starts_on', 'agm_deadline', 'report_deadline', 'closed_at', 'opened_by'])]
class AgmPeriod extends Model
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'agm_deadline' => 'date', 'report_deadline' => 'date', 'closed_at' => 'datetime'];
    }

    /** Open = not closed by SLD and today is between the start and the report deadline. */
    public function isOpen(): bool
    {
        return $this->closed_at === null && today()->betweenIncluded($this->starts_on, $this->report_deadline);
    }

    /** @return BelongsTo<AcademicSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    /** @return HasMany<Agm, $this> */
    public function agms(): HasMany
    {
        return $this->hasMany(Agm::class);
    }
}
