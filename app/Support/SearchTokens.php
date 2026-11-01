<?php

namespace App\Support;

/**
 * Splits a search into words that match transaction IDs however they are typed: case and punctuation are
 * ignored, 2026-11-01 / 1/11/2026 become 20261101 and 14:30 / 2:30pm become 1430. Every word must match.
 */
final class SearchTokens
{
    /** @return list<string> upper-case letters and digits only */
    public static function from(string $query): array
    {
        $tokens = [];
        foreach (preg_split('/[\s_]+/', trim($query)) ?: [] as $word) {
            $word = self::date($word) ?? self::time($word) ?? $word;
            $clean = strtoupper((string) preg_replace('/[^a-z0-9]/i', '', $word));
            if ($clean !== '') {
                $tokens[] = $clean;
            }
        }

        return array_values(array_unique($tokens));
    }

    /** True when every word of the query appears in the text (compared without punctuation). */
    public static function matches(string $query, string $text): bool
    {
        $haystack = strtoupper((string) preg_replace('/[^a-z0-9]/i', '', $text));
        foreach (self::from($query) as $token) {
            if (! str_contains($haystack, $token)) {
                return false;
            }
        }

        return true;
    }

    private static function date(string $word): ?string
    {
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $word, $m)) {
            return sprintf('%04d%02d%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $word, $m)) {
            return sprintf('%04d%02d%02d', $m[3], $m[2], $m[1]);
        }

        return null;
    }

    private static function time(string $word): ?string
    {
        if (! preg_match('/^(\d{1,2})[:.](\d{2})\s*(am|pm)?$/i', $word, $m)) {
            return null;
        }
        $suffix = strtolower($m[3] ?? '');
        $hour = $suffix === '' ? (int) $m[1] : (int) $m[1] % 12 + ($suffix === 'pm' ? 12 : 0);

        return sprintf('%02d%02d', $hour, $m[2]);
    }
}
