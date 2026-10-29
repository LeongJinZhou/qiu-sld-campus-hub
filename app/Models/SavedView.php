<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'page', 'name', 'filters'])]
class SavedView extends Model
{
    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
