<?php

namespace App\Support;

/**
 * Keeps posts and comments civil: profanity and insults in English, Malay and common Manglish/Chinese-dialect
 * slang are refused, including spaced, underscored, stretched or leet-speak spellings (b0d0h, f.u.c.k, f_u_c_k,
 * fuuuck, fvck). Words are matched whole, so "class", "bitcoin" or "Scunthorpe" are fine; a few unmistakable
 * stems (STEMS) are also caught inside run-together words ("fuckingshit").
 */
final class ContentFilter
{
    /** @var list<string> */
    private const WORDS = [
        // English
        'fuck', 'fucking', 'fucker', 'fucked', 'fck', 'fk', 'fuk', 'fuq', 'fvck', 'phuck', 'fcuk', 'wtf', 'stfu', 'gtfo', 'shit', 'shyt', 'sht', 'twat', 'prick', 'wanker', 'skank', 'bullshit', 'bitch', 'bastard', 'asshole', 'dick', 'dickhead',
        'cunt', 'slut', 'whore', 'motherfucker', 'retard', 'retarded', 'idiot', 'moron', 'stupid', 'dumbass', 'loser', 'scumbag', 'pussy', 'nigger', 'nigga', 'faggot',
        'kys', 'kill yourself',
        // Malay
        'bodoh sombong', 'otak udang', 'anak haram', 'bapak kau', 'kepala bapak', 'kepala hotak', 'pundek', 'pepek', 'kote', 'kotek', 'konek', 'butuh',
        'pondan', 'bapuk', 'gampang', 'lahanat', 'laknat', 'bahlul', 'bengong', 'tak guna', 'pariah', 'keling', 'kepala butoh',
        'bodoh', 'bodo', 'babi', 'sial', 'celaka', 'pukimak', 'puki', 'pantat', 'lancau', 'kimak', 'butoh', 'bangsat', 'anjing', 'haram jadah', 'haramjadah', 'sundal', 'bangang', 'bebal', 'goblok', 'tolol', 'mampus', 'jubur', 'keparat',
        // Chinese dialect / Manglish
        'lanjiao', 'kanninnah', 'kannina', 'kan ni na', 'knn', 'cibai', 'chibai', 'chee bai', 'ccb', 'pukinangina', 'diu', 'diu lei', 'puki mak',
        'sohai', 'sor hai', 'hamsap', 'ham sap', 'nabeh', 'nabei', 'pok kai', 'puk kai', 'hum ka chan', 'kaninabu',
    ];

    /** Unmistakable stems, caught even inside run-together words. @var list<string> */
    private const STEMS = ['fuck', 'fvck', 'phuck', 'motherf', 'pukimak', 'lancau', 'lanjiao', 'cibai', 'chibai', 'kanninnah', 'nigger', 'faggot', 'haramjadah', 'sohai', 'pundek', 'kaninabu'];

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
        // Every non-letter is a separator, so f_u_c_k and f-u-c-k read like f u c k.
        $singles = self::singleLetterRuns($spaced);
        $letters = (string) preg_replace('/[^a-z]/', '', $plain);
        $squeezed = (string) preg_replace('/([a-z])\1+/', '$1', $letters);

        $found = [];
        foreach (self::WORDS as $word) {
            if (str_contains($spaced, ' '.$word.' ') || str_contains($collapsed, ' '.$word.' ') || in_array(str_replace(' ', '', $word), $singles, true)) {
                $found[] = $word;
            }
        }

        foreach (self::STEMS as $stem) {
            if (str_contains($letters, $stem) || str_contains($squeezed, $stem)) {
                $found[] = $stem;
            }
        }

        return array_values(array_unique($found));
    }

    public static function isClean(?string $text): bool
    {
        return self::offences($text) === [];
    }

    /** "f u c k" or "b.o.d.o.h": runs of single letters joined back into a word. @return list<string> */
    private static function singleLetterRuns(string $text): array
    {
        preg_match_all('/(?:(?<![a-z])[a-z](?![a-z])[^a-z]{1,3}){2,}(?<![a-z])[a-z](?![a-z])/', $text, $m);

        return array_map(fn (string $run) => (string) preg_replace('/[^a-z]/', '', $run), $m[0]);
    }
}
