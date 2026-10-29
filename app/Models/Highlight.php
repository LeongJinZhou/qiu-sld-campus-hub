<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['club_id', 'title', 'sort'])]
class Highlight extends Model
{
    /** @return HasMany<HighlightItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(HighlightItem::class)->orderBy('sort');
    }
}
