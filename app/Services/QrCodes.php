<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Zxing\QrReader;

class QrCodes
{
    public function svg(string $url): string
    {
        $svg = (string) QrCode::format('svg')->size(240)->margin(0)->errorCorrection('M')->generate($url);

        // Inlined into the page, so drop the XML prolog.
        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }

    /** Reads the QR code in a photo, or null when there is none or it is unreadable. */
    public function decode(string $path): ?string
    {
        try {
            // TRY_HARDER: without it ~1 in 10 clean phone photos of the QR fail to decode.
            $text = (new QrReader($path))->text(['TRY_HARDER' => true]);

            return is_string($text) && $text !== '' ? $text : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** The personal profile QR token in a photo (…/p/{token}), or null. */
    public function profileToken(string $path): ?string
    {
        $text = $this->decode($path);

        return $text !== null && preg_match('#/p/([A-Za-z0-9_-]+)#', $text, $m) ? $m[1] : null;
    }
}
