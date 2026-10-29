<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired when the last step of a chain approves. */
class DocumentApproved
{
    use Dispatchable;

    public function __construct(public readonly Document $document) {}
}
