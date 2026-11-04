<?php

namespace App\Support;

/** A safe download file name: no path separators or control characters, which the Content-Disposition header refuses. */
final class DownloadName
{
    public static function safe(string $name): string
    {
        return trim((string) preg_replace(['/[\/\\\\]+/', '/[\x00-\x1F\x7F]+/'], ['-', ''], $name));
    }
}
