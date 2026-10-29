<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Intake number -> month, only for dummy student IDs that do not carry the month. */
#[Fillable(['intake_number', 'month'])]
class IntakeMap extends Model
{
    protected $table = 'intake_map';

    protected $primaryKey = 'intake_number';

    public $incrementing = false;

    /** @return array<int, int> */
    public static function months(): array
    {
        return static::pluck('month', 'intake_number')->all();
    }
}
