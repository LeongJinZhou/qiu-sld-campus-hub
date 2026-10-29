<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['election_position_id', 'candidate_id', 'voter_id', 'created_at'])]
class Vote extends Model
{
    public const UPDATED_AT = null;
}
