<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodes
{
    public function svg(string $url): string
    {
        $svg = (string) QrCode::format('svg')->size(240)->margin(0)->errorCorrection('M')->generate($url);

        // Inlined into the page, so drop the XML prolog.
        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
