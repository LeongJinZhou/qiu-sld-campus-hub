<?php

namespace App\Services\Documents;

use App\Models\Signature;

/** A signature block on an official form: who, as what, and the drawn signature once signed. */
final readonly class Signer
{
    public function __construct(public ?string $name, public string $position, public ?Signature $signature = null) {}
}
