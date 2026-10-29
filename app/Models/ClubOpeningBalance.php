<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Entered by SLD before a club can use finance; the ledger's first line "Opening Acc". */
#[Fillable(['club_id', 'amount_sen', 'as_of', 'note', 'set_by'])]
class ClubOpeningBalance extends Model
{
    protected function casts(): array
    {
        return ['as_of' => 'date'];
    }
}
