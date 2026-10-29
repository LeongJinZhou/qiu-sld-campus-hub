<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['club_id', 'user_id', 'type', 'venue', 'date', 'starts_at', 'ends_at', 'semester', 'weekday', 'purpose', 'items', 'activity', 'status', 'sld_note', 'decided_by', 'decided_at'])]
class VenueBooking extends Model
{
    public const TYPES = ['la_place' => 'La Place booth', 'semester' => 'Semester permanent venue', 'big_room' => 'One-off big room'];

    protected function casts(): array
    {
        return ['date' => 'date', 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
