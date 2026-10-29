<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['club_id', 'entry_date', 'description', 'income_sen', 'expense_sen', 'source_type', 'source_id', 'receipt_id', 'created_by'])]
class LedgerEntry extends Model
{
    protected function casts(): array
    {
        return ['entry_date' => 'date', 'income_sen' => 'integer', 'expense_sen' => 'integer'];
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
