<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An application to start a new club: President applies → Advisor signs → SLD approves (then the club opens). */
#[Fillable(['applicant_id', 'advisor_id', 'data', 'status', 'applicant_signature_id', 'advisor_signature_id', 'advisor_signed_at', 'decided_by', 'decided_at', 'note', 'club_id'])]
class ClubApplication extends Model
{
    public const STATUSES = ['advisor' => 'Waiting for the advisor', 'sld' => 'Waiting for SLD', 'approved' => 'Approved', 'returned' => 'Returned for changes', 'rejected' => 'Rejected'];

    protected function casts(): array
    {
        return ['data' => 'array', 'advisor_signed_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
