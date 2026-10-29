<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['trigger', 'triggered_by', 'records_seen', 'upserted', 'error', 'started_at', 'finished_at'])]
class CamsysSyncRun extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
