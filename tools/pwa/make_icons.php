<?php

/*
 * Builds the PWA icons in public/icons from the QIU Student Activity logo (public/brand).
 * Usage: php tools/pwa/make_icons.php
 */

$root = dirname(__DIR__, 2);
$logo = imagecreatefrompng("{$root}/public/brand/QIU_Student_Activity_Black.png");

// [file, size, logo share of the canvas]. Maskable icons keep the logo inside the 80% safe zone.
$icons = [['icon-192.png', 192, 0.84], ['icon-512.png', 512, 0.84], ['icon-maskable-512.png', 512, 0.62], ['apple-touch-icon.png', 180, 0.8]];
foreach ($icons as [$file, $size, $share]) {
    $canvas = imagecreatetruecolor($size, $size);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    $scale = min($size * $share / imagesx($logo), $size * $share / imagesy($logo));
    $w = (int) round(imagesx($logo) * $scale);
    $h = (int) round(imagesy($logo) * $scale);
    imagecopyresampled($canvas, $logo, intdiv($size - $w, 2), intdiv($size - $h, 2), 0, 0, $w, $h, imagesx($logo), imagesy($logo));
    imagepng($canvas, "{$root}/public/icons/{$file}");
    echo "Wrote public/icons/{$file}\n";
}

// Web logos: the brand PNGs are 4500px squares with wide empty margins, so crop to the artwork for headers.
foreach (['Black', 'White'] as $tone) {
    $src = imagecreatefrompng("{$root}/public/brand/QIU_Student_Activity_{$tone}.png");
    [$w, $h] = [imagesx($src), imagesy($src)];
    [$minX, $minY, $maxX, $maxY] = [$w, $h, 0, 0];
    for ($y = 0; $y < $h; $y += 4) {
        for ($x = 0; $x < $w; $x += 4) {
            if (((imagecolorat($src, $x, $y) >> 24) & 0x7F) < 100) {
                [$minX, $minY, $maxX, $maxY] = [min($minX, $x), min($minY, $y), max($maxX, $x), max($maxY, $y)];
            }
        }
    }
    $pad = 40;
    $cw = $maxX - $minX + 2 * $pad;
    $ch = $maxY - $minY + 2 * $pad;
    $outW = 640;
    $outH = (int) round($ch * $outW / $cw);
    $out = imagecreatetruecolor($outW, $outH);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $src, 0, 0, $minX - $pad, $minY - $pad, $outW, $outH, $cw, $ch);
    imagepng($out, "{$root}/public/brand/student-activity-".strtolower($tone).'.png');
    echo 'Wrote public/brand/student-activity-'.strtolower($tone).".png\n";
}
