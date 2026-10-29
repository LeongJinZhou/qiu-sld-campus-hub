<?php

namespace App\Http\Controllers;

use App\Models\Signature;
use Inertia\Inertia;
use Inertia\Response;

/** Public check of a signature's verification code. Shows the record, never the signature image. */
class VerifyController extends Controller
{
    public function __invoke(string $code): Response
    {
        $signature = Signature::with('document.club', 'document.event')->where('verification_code', strtoupper($code))->first();

        return Inertia::render('Verify/Show', [
            'code' => strtoupper($code),
            'record' => $signature ? [
                'signer' => $signature->signer_name,
                'position' => $signature->position_at_time,
                'signed_at' => $signature->signed_at->toDayDateTimeString(),
                'document' => $signature->document?->type->label(),
                'club' => $signature->document?->club->name,
                'event' => $signature->document?->event?->name,
                'status' => $signature->document?->status->value,
            ] : null,
        ]);
    }
}
