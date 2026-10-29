<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id', 'user_id', 'emergency_name', 'emergency_mobile_phone', 'emergency_work_phone', 'emergency_relationship',
    'alternate_name', 'alternate_phone', 'contact_number', 'address', 'signature_id', 'signed_at',
])]
#[Hidden(['emergency_mobile_phone', 'emergency_work_phone', 'alternate_phone', 'contact_number', 'address'])]
class Indemnity extends Model
{
    protected function casts(): array
    {
        return [
            'emergency_mobile_phone' => 'encrypted',
            'emergency_work_phone' => 'encrypted',
            'alternate_phone' => 'encrypted',
            'contact_number' => 'encrypted',
            'address' => 'encrypted',
            'signed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Signature, $this> */
    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }
}
