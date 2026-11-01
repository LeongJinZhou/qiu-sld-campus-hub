<?php

namespace App\Support;

/**
 * Keeps posts and comments civil: profanity and insults in English, Malay and common Manglish/Chinese-dialect
 * slang are refused, including spaced, stretched or leet-speak spellings (b0d0h, f.u.c.k, fuuuck). Words are
 * matched whole, so "class", "bitcoin" or "Scunthorpe" are fine.
 */
final class ContentFilter
{
    /** @var list<string> */
    private const WORDS = [
        // English
        'fuck', 'fucking', 'fucker', 'fck', 'fk', 'wtf', 'stfu', 'shit', 'bullshit', 'bitch', 'bastard', 'asshole', 'dick', 'dickhead',
        'cunt', 'slut', 'whore', 'motherfucker', 'retard', 'retarded', 'idiot', 'moron', 'stupid', 'dumbass', 'loser', 'scumbag', 'pussy', 'nigger', 'nigga', 'faggot',
        'kys', 'kill yourself',
        // Malay
        'bodoh', 'bodo', 'babi', 'sial', 'celaka', 'pukimak', 'puki', 'pantat', 'lancau', 'kimak', 'butoh', 'bangsat', 'anjing', 'haram jadah', 'haramjadah', 'sundal', 'bangang', 'bebal', 'goblok', 'tolol', 'mampus', 'jubur', 'keparat',
        // Chinese dialect / Manglish
        'lanjiao', 'kanninnah', 'knn', 'cibai', 'chibai', 'ccb', 'pukinangina', 'diu', 'puki mak',
    ];

    private const LEET = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '!' => 'i'];

    /** @return list<string> the offending words found (empty when the text is fine) */
    public static function offences(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }
        $plain = strtolower(strtr(mb_strtolower($text), self::LEET));
        // Whole words as written, stretched letters collapsed ("fuuuck"), and "f u c k" / "b.o.d.o.h" joined back.
        $spaced = ' '.trim((string) preg_replace('/[^a-z]+/', ' ', $plain)).' ';
        $collapsed = (string) preg_replace('/([a-z])\1{2,}/', '$1', $spaced);
        $singles = self::singleLetterRuns($plain);

        $found = [];
        foreach (self::WORDS as $word) {
            if (str_contains($spaced, ' '.$word.' ') || str_contains($collapsed, ' '.$word.' ') || in_array(str_replace(' ', '', $word), $singles, true)) {
                $found[] = $word;
            }
        }

        return $found;
    }

    public static function isClean(?string $text): bool
    {
        return self::offences($text) === [];
    }

    /** "f u c k" or "b.o.d.o.h": runs of single letters joined back into a word. @return list<string> */
    private static function singleLetterRuns(string $text): array
    {
        preg_match_all('/(?:\b[a-z]\b[^a-z]{1,3}){2,}\b[a-z]\b/', $text, $m);

        return array_map(fn (string $run) => (string) preg_replace('/[^a-z]/', '', $run), $m[0]);
    }
}
