<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['club_id', 'agm_period_id', 'session_id', 'starts_at', 'ends_at', 'venue', 'meet_link', 'google_event_id', 'attendance_secret', 'organiser_id', 'package_submitted_at'])]
#[Hidden(['attendance_secret'])]
class Agm extends Model
{
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'package_submitted_at' => 'datetime'];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<AgmPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AgmPeriod::class, 'agm_period_id');
    }

    /** @return BelongsTo<AcademicSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agm_attendance')->withPivot('method', 'attended_at');
    }

    /** @return HasOne<Document, $this> */
    public function report(): HasOne
    {
        return $this->hasOne(Document::class)->where('type', DocumentType::AgmReport);
    }

    /** @return HasOne<Election, $this> */
    public function election(): HasOne
    {
        return $this->hasOne(Election::class);
    }
}
