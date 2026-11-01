<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Query-string values exactly as the browser sent them can be arrays (?q[]=x) or nonsense. These helpers return
 * plain, bounded values so a malformed link gives a normal page, never a server error.
 */
final class QueryInput
{
    public static function text(Request $request, string $key, int $max = 200, string $default = ''): string
    {
        $value = $request->query($key, $default);

        return is_string($value) || is_int($value) ? mb_substr(trim((string) $value), 0, $max) : $default;
    }

    /** @param list<string> $allowed */
    public static function oneOf(Request $request, string $key, array $allowed, ?string $default = null): ?string
    {
        $value = $request->query($key);

        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    public static function int(Request $request, string $key, int $default, int $min, int $max): int
    {
        $value = $request->query($key);

        return is_string($value) && ctype_digit($value) ? max($min, min($max, (int) $value)) : $default;
    }

    /** A YYYY-MM month within 1900–2100, or null. */
    public static function month(Request $request, string $key): ?string
    {
        $value = $request->query($key);
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})$/', $value, $m)) {
            return null;
        }

        return (int) $m[1] >= 1900 && (int) $m[1] <= 2100 && (int) $m[2] >= 1 && (int) $m[2] <= 12 ? $value : null;
    }
}
