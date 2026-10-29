<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['club_id', 'question', 'answer', 'sort'])]
class ClubFaq extends Model {}
