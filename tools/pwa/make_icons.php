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
