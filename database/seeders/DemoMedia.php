<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Storage;

/** Generated SVG artwork for local demo data (club logos, event posters). No photos of people. */
final class DemoMedia
{
    private const PALETTES = [
        ['#FDDA1A', '#FD8F1A'], ['#1CBDFD', '#1F6FFF'], ['#E01CEB', '#FD8F1A'],
        ['#16A34A', '#1CBDFD'], ['#FD8F1A', '#E01CEB'], ['#1F6FFF', '#E01CEB'],
    ];

    public static function logo(string $name, int $index, string $glyph): string
    {
        [$a, $b] = self::PALETTES[$index % count(self::PALETTES)];
        $initials = self::initials($name);
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
          <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$a}"/><stop offset="1" stop-color="{$b}"/></linearGradient></defs>
          <rect width="200" height="200" fill="url(#g)"/>
          <circle cx="160" cy="40" r="60" fill="#fff" opacity=".12"/>
          <text x="100" y="92" text-anchor="middle" font-family="Poppins, Arial, sans-serif" font-size="44">{$glyph}</text>
          <text x="100" y="150" text-anchor="middle" font-family="Poppins, Arial, sans-serif" font-weight="700" font-size="46" fill="#fff" letter-spacing="2">{$initials}</text>
        </svg>
        SVG;

        return self::put("club-logos/demo-{$index}.svg", $svg);
    }

    public static function poster(string $title, string $club, string $when, string $venue, string $cca, int $index): string
    {
        [$a, $b] = self::PALETTES[($index + 2) % count(self::PALETTES)];
        $lines = array_map(fn ($l) => htmlspecialchars($l, ENT_XML1), explode("\n", wordwrap(mb_strtoupper($title), 16, "\n", true)));
        $titleSvg = '';
        foreach ($lines as $i => $line) {
            $titleSvg .= '<text x="60" y="'.(330 + $i * 96).'" font-size="88" font-weight="800" fill="#fff">'.$line.'</text>';
        }
        $logo = base64_encode((string) file_get_contents(public_path('brand/QIU_Student_Activity_Black.png')));
        [$club, $when, $venue, $cca] = array_map(fn ($v) => htmlspecialchars($v, ENT_XML1), [$club, $when, $venue, $cca]);
        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1080 1350" font-family="Poppins, Arial, sans-serif">
          <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$a}"/><stop offset="1" stop-color="{$b}"/></linearGradient></defs>
          <rect width="1080" height="1350" fill="url(#g)"/>
          <circle cx="900" cy="1150" r="380" fill="#fff" opacity=".08"/><circle cx="120" cy="200" r="220" fill="#fff" opacity=".08"/>
          <image href="data:image/png;base64,{$logo}" x="50" y="30" width="380" height="190" preserveAspectRatio="xMinYMid meet"/>
          <text x="1020" y="120" text-anchor="end" font-size="34" font-weight="600" fill="#fff">{$club}</text>
          {$titleSvg}
          <rect x="60" y="860" width="960" height="230" rx="28" fill="#1a1a1f" opacity=".85"/>
          <text x="100" y="940" font-size="40" font-weight="600" fill="#fff">{$when}</text>
          <text x="100" y="1010" font-size="36" fill="#fff">{$venue}</text>
          <text x="100" y="1060" font-size="30" fill="#FDDA1A" font-weight="600">{$cca} · Register on QIU Campus Hub</text>
          <text x="60" y="1290" font-size="28" fill="#fff" opacity=".9">Student Life Division · Quest International University</text>
        </svg>
        SVG;

        return self::put("posters/demo-{$index}.svg", $svg);
    }

    private static function initials(string $name): string
    {
        preg_match_all('/\b[A-Z]/', $name, $m);

        return implode('', array_slice($m[0], 0, 3));
    }

    private static function put(string $path, string $svg): string
    {
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
