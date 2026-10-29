<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Digital signature. The image lives on the private disk; the verification code opens /verify/{code}. */
#[Fillable(['document_id', 'approval_id', 'document_version_id', 'user_id', 'signer_name', 'position_at_time', 'image_path', 'verification_code', 'signed_at'])]
#[Hidden(['image_path'])]
class Signature extends Model
{
    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
