<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['club_id', 'path', 'original_name', 'uploaded_by'])]
#[Hidden(['path'])]
class Receipt extends Model {}
