<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['programme_code', 'abbreviation'])]
class ProgrammeAbbrev extends Model
{
    protected $table = 'programme_abbrev';

    protected $primaryKey = 'programme_code';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function for(string $programmeCode): ?string
    {
        return Cache::rememberForever('programme_abbrev', fn () => static::pluck('abbreviation', 'programme_code')->all())[$programmeCode] ?? null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('programme_abbrev'));
        static::deleted(fn () => Cache::forget('programme_abbrev'));
    }
}
