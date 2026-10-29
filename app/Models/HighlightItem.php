<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['highlight_id', 'media_path', 'sort'])]
class HighlightItem extends Model
{
    public function url(): string
    {
        return Storage::disk('public')->url($this->media_path);
    }
}
