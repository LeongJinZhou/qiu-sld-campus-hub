<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** Treasurer or vendor account for fund withdrawals. Bank details are encrypted at rest. */
#[Fillable(['club_id', 'kind', 'name', 'email', 'contact_no', 'bank_name', 'account_holder', 'account_number'])]
#[Hidden(['account_number', 'account_holder', 'contact_no'])]
class Payee extends Model
{
    protected function casts(): array
    {
        return ['contact_no' => 'encrypted', 'account_holder' => 'encrypted', 'account_number' => 'encrypted'];
    }

    /** "•••• 1234" for screens; the full number only goes on the official form. */
    public function maskedAccount(): string
    {
        return '•••• '.substr((string) $this->account_number, -4);
    }
}
