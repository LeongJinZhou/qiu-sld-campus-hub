<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['album_id', 'path', 'uploaded_by'])]
class AlbumPhoto extends Model
{
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
