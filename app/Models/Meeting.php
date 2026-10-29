<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['club_id', 'session_id', 'kind', 'title', 'starts_at', 'ends_at', 'venue', 'meet_link', 'google_event_id', 'organiser_id'])]
class Meeting extends Model
{
    public const KINDS = ['committee' => 'Committee meeting', 'activity' => 'Weekly activity'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<User, $this> */
    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organiser_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_attendance')->withPivot('present');
    }

    /** @return HasOne<Document, $this> */
    public function minutes(): HasOne
    {
        return $this->hasOne(Document::class)->where('type', DocumentType::Minutes);
    }
}
