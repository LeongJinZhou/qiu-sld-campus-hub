<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One turn of a person's private assistant history; they can delete any of it. */
class AssistantMessage extends Model
{
    protected $fillable = ['user_id', 'role', 'content', 'links'];

    protected function casts(): array
    {
        return ['links' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
