<?php

namespace App\Services\Approvals;

use App\Models\Signature;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Stores a SignaturePad PNG on the private disk with a server timestamp and a verification code. */
class SignatureStore
{
    private const MAX_BYTES = 512 * 1024;

    /** @param array{document_id?: int, approval_id?: int, document_version_id?: int} $links */
    public function store(User $user, string $dataUrl, string $position, array $links = []): Signature
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw new DomainException('Please sign in the signature box.');
        }
        $png = base64_decode($m[1], true);
        $info = $png !== false && strlen($png) <= self::MAX_BYTES ? @getimagesizefromstring($png) : false;
        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            throw new DomainException('The signature image is not valid.');
        }

        $path = 'signatures/'.Str::uuid().'.png';
        Storage::disk('local')->put($path, $png);

        return Signature::create([
            ...$links,
            'user_id' => $user->id,
            'signer_name' => $user->name,
            'position_at_time' => $position,
            'image_path' => $path,
            'verification_code' => $this->code(),
            'signed_at' => now(),
        ]);
    }

    private function code(): string
    {
        do {
            $code = Str::upper(Str::random(12));
        } while (Signature::where('verification_code', $code)->exists());

        return $code;
    }
}
