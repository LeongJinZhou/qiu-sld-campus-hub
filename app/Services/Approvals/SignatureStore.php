<?php

namespace App\Services\Approvals;

use App\Models\Signature;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores a drawn e-signature: the PNG on the private disk, the raw strokes recorded from mouse, touch or
 * stylus as evidence, a server timestamp and a verification code.
 */
class SignatureStore
{
    private const MAX_BYTES = 512 * 1024;

    private const MAX_STROKE_JSON = 300 * 1024;

    private const MAX_POINTS = 20000;

    /** Minimum drawing: the strokes must cover at least this much width + height, and travel this far. */
    private const MIN_SPREAD_PX = 20;

    private const MIN_TRAVEL_PX = 40;

    /** @param array{document_id?: int, approval_id?: int, document_version_id?: int} $links */
    public function store(User $user, string $dataUrl, string $position, array $links = [], ?string $strokesJson = null): Signature
    {
        $strokes = $strokesJson !== null ? $this->strokes($strokesJson) : null;

        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw new DomainException('Please sign in the signature box.');
        }
        $png = base64_decode($m[1], true);
        $info = $png !== false && strlen($png) <= self::MAX_BYTES ? @getimagesizefromstring($png) : false;
        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            throw new DomainException('The signature image is not valid.');
        }
        if ($this->isEmptyImage($png)) {
            throw new DomainException('The signature box is empty. Please sign.');
        }

        $path = 'signatures/'.Str::uuid().'.png';
        Storage::disk('local')->put($path, $png);

        return Signature::create([
            ...$links,
            'user_id' => $user->id,
            'signer_name' => $user->name,
            'position_at_time' => $position,
            'image_path' => $path,
            'strokes' => $strokes,
            'input_device' => $strokes['device'] ?? null,
            'verification_code' => $this->code(),
            'signed_at' => now(),
        ]);
    }

    /**
     * Validates the recorded strokes: {w, h, device, strokes: [[[x, y, t, pressure], ...], ...]}.
     *
     * @return array<string, mixed>
     */
    private function strokes(string $json): array
    {
        $data = strlen($json) <= self::MAX_STROKE_JSON ? json_decode($json, true) : null;
        $strokes = is_array($data) ? ($data['strokes'] ?? null) : null;
        if (! is_array($strokes) || ! in_array($data['device'] ?? null, ['mouse', 'touch', 'pen'], true)) {
            throw new DomainException('The signature recording is not valid.');
        }

        $points = 0;
        foreach ($strokes as $stroke) {
            if (! is_array($stroke)) {
                throw new DomainException('The signature recording is not valid.');
            }
            foreach ($stroke as $point) {
                if (! is_array($point) || count($point) !== 4 || array_filter($point, fn ($v) => ! is_int($v) && ! is_float($v))) {
                    throw new DomainException('The signature recording is not valid.');
                }
                $points++;
            }
        }
        if ($points < 10) {
            throw new DomainException('Please provide a complete signature in the signature box.');
        }
        // A real signature moves: points that never leave a tiny spot (a tap, or made-up data) are refused.
        $all = array_merge(...array_values($strokes));
        $xs = array_column($all, 0);
        $ys = array_column($all, 1);
        $travel = 0.0;
        foreach ($strokes as $stroke) {
            for ($i = 1; $i < count($stroke); $i++) {
                $travel += hypot($stroke[$i][0] - $stroke[$i - 1][0], $stroke[$i][1] - $stroke[$i - 1][1]);
            }
        }
        if (max($xs) - min($xs) + max($ys) - min($ys) < self::MIN_SPREAD_PX || $travel < self::MIN_TRAVEL_PX) {
            throw new DomainException('That does not look like a signature. Please sign with your finger, pen or mouse.');
        }
        if ($points > self::MAX_POINTS) {
            throw new DomainException('The signature recording is too long.');
        }

        return ['w' => (float) ($data['w'] ?? 0), 'h' => (float) ($data['h'] ?? 0), 'device' => $data['device'], 'strokes' => $strokes];
    }

    /** Fully transparent (nothing drawn) images are empty. Every pixel is checked, stopping at the first ink. */
    private function isEmptyImage(string $png): bool
    {
        $image = @imagecreatefromstring($png);
        if ($image === false) {
            return true;
        }
        $w = imagesx($image);
        $h = imagesy($image);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) < 120) {
                    return false;
                }
            }
        }

        return true;
    }

    private function code(): string
    {
        do {
            $code = Str::upper(Str::random(12));
        } while (Signature::where('verification_code', $code)->exists());

        return $code;
    }
}
