<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/** Decodes an uploaded image and re-encodes it as a plain PNG, which drops metadata and any embedded payload. */
final class ImageSanitizer
{
    public static function toPng(UploadedFile $file, string $field, int $maxSide = 512): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages([$field => 'Image processing (GD extension) is not available on this server.']);
        }

        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;

        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 25_000_000) {
            throw ValidationException::withMessages([$field => 'The file is not a valid PNG, JPG or WebP image.']);
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image) {
            throw ValidationException::withMessages([$field => 'The image could not be read.']);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) > $maxSide) {
            $scale = $maxSide / max($width, $height);
            $resized = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            imagedestroy($image);
            $image = $resized;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
