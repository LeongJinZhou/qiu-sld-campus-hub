<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'background_color', 'title_color', 'body_color', 'font', 'background_path', 'is_default'])]
class ReportTheme extends Model
{
    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
