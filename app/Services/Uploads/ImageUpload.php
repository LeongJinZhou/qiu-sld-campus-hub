<?php

namespace App\Services\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores an uploaded photo by decoding and re-encoding it with GD. Only real pixels survive: EXIF (including
 * GPS location), embedded scripts and file-type tricks are dropped, and oversized photos are scaled down.
 */
class ImageUpload
{
    public const MAX_EDGE = 2048;

    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages(['photo' => 'That file is not a readable image.']);
        }
        $source = $this->orient($source, $file);

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $image = $source;
        if ($scale < 1) {
            $image = imagescale($source, (int) round($width * $scale), (int) round($height * $scale)) ?: $source;
        }

        // PNG keeps transparency (logos); everything else becomes JPEG.
        $png = $file->getMimeType() === 'image/png';
        ob_start();
        if ($png) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, null, 6);
        } else {
            imagejpeg($image, null, 88);
        }
        $path = trim($directory, '/').'/'.Str::random(40).($png ? '.png' : '.jpg');
        Storage::disk($disk)->put($path, (string) ob_get_clean());

        return $path;
    }

    /** Phone photos are often stored sideways with an EXIF rotation flag; apply it before the flag is dropped. */
    private function orient(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }
        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
