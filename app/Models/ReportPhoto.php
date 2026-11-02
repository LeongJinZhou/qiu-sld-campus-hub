<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/** An evidence photo attached to a weekly activity report (meeting), an AGM or an event. */
#[Fillable(['photoable_type', 'photoable_id', 'path', 'uploaded_by'])]
class ReportPhoto extends Model
{
    /** @return MorphTo<Model, $this> */
    public function photoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
