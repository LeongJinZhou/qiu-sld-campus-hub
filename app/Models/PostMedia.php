<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['post_id', 'path', 'sort'])]
class PostMedia extends Model
{
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
