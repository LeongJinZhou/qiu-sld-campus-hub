<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'number', 'data', 'created_by', 'created_at'])]
class DocumentVersion extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
