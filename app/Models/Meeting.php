<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['club_id', 'session_id', 'kind', 'audience', 'pax', 'title', 'starts_at', 'ends_at', 'venue', 'meet_link', 'google_event_id', 'organiser_id'])]
#[Hidden(['attendance_secret', 'google_event_id', 'checkin_qr_path', 'checkout_qr_path'])]
class Meeting extends Model
{
    /** Check-in opens this long before the start; check-out stays open this long after the end. */
    public const CHECK_IN_EARLY_MINUTES = 30;

    public const CHECK_OUT_LATE_MINUTES = 120;

    public const KINDS = ['committee' => 'Committee meeting', 'activity' => 'Weekly activity'];

    /** Who a weekly activity is for; a committee meeting is always for the committee and advisors. */
    public const AUDIENCES = ['committee' => 'Committee & advisors', 'members' => 'All members'];

    protected static function booted(): void
    {
        static::creating(fn (Meeting $m) => $m->attendance_secret ??= Str::random(40));
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'pax' => 'integer'];
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
        return $this->belongsToMany(User::class, 'meeting_attendance')->withPivot('present', 'checked_in_at', 'checked_out_at', 'method');
    }

    /** @return HasOne<Document, $this> */
    public function minutes(): HasOne
    {
        return $this->hasOne(Document::class)->where('type', DocumentType::Minutes);
    }
}
