<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\ChainRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['document_id', 'step_no', 'role', 'approver_user_id', 'decision', 'comment', 'due_at', 'decided_at'])]
class Approval extends Model
{
    protected function casts(): array
    {
        return [
            'role' => ChainRole::class,
            'decision' => ApprovalDecision::class,
            'due_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    /** @return HasOne<Signature, $this> */
    public function signature(): HasOne
    {
        return $this->hasOne(Signature::class);
    }
}
