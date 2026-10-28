<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/** Shared GD normalization; callers own authorization and filename policy. */
class WeddingImageProcessor
{
    public const MAX_UPLOAD_BYTES = 5242880;

    public const MAX_DIMENSION = 6000;

    public const MAX_PIXELS = 12000000;

    public const MAX_OUTPUT_DIMENSION = 1600;

    public const WEBP_QUALITY = 82;

    public function normalize(UploadedFile $file, string $field = 'image'): string
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([$field => 'Your photo did not finish uploading. Select it again and retry.']);
        }

        if (! extension_loaded('gd') || ! function_exists('exif_read_data') || ! function_exists('imagewebp')
            || ! (imagetypes() & IMG_JPG) || ! (imagetypes() & IMG_PNG) || ! (imagetypes() & IMG_WEBP)
            || (ini_get('memory_limit') !== '-1' && ini_parse_quantity(ini_get('memory_limit')) < 256 * 1024 * 1024)) {
            throw ValidationException::withMessages([$field => 'Photo uploads are temporarily unavailable. Please contact support.']);
        }

        $source = @file_get_contents($file->getPathname(), false, null, 0, self::MAX_UPLOAD_BYTES + 1);
        $size = $source === false ? false : @getimagesizefromstring($source);
        $mime = $source === false ? false : (new \finfo(FILEINFO_MIME_TYPE))->buffer($source);
        if (! $size || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $file->getSize() > self::MAX_UPLOAD_BYTES || strlen($source) > self::MAX_UPLOAD_BYTES
            || $mime !== image_type_to_mime_type($size[2])
            || max($size[0], $size[1]) > self::MAX_DIMENSION || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages([$field => 'Choose a valid JPEG, PNG, or WebP photo up to 5 MiB and 12 million pixels.']);
        }

        $image = @imagecreatefromstring($source);
        if (! $image) {
            throw ValidationException::withMessages([$field => 'This photo could not be read. Please choose another image.']);
        }

        if ($size[2] === IMAGETYPE_JPEG) {
            $metadata = @exif_read_data($file->getPathname(), 'IFD0');
            $image = $this->orient($image, (int) ($metadata['Orientation'] ?? 1));
        } else {
            $image = $this->orient($image, $this->embeddedOrientation($source, $size[2]));
        }

        $scale = min(1, self::MAX_OUTPUT_DIMENSION / max(imagesx($image), imagesy($image)));
        $output = imagecreatetruecolor(max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), imagesx($image), imagesy($image));
        unset($image);

        ob_start();
        try {
            $encoded = imagewebp($output, null, self::WEBP_QUALITY);
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if (! $encoded || ! $bytes || strlen($bytes) > self::MAX_UPLOAD_BYTES) {
            throw ValidationException::withMessages([$field => 'This photo could not be processed. Please choose another image.']);
        }

        return $bytes;
    }

    private function orient(GdImage $image, int $orientation): GdImage
    {
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            6, 7 => -90,
            5, 8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : imagerotate($image, $angle, 0);
    }

    /** Read bounded PNG/WebP EXIF chunks with the existing PHP TIFF reader; never retain metadata. */
    private function embeddedOrientation(string $source, int $type): int
    {
        $offset = $type === IMAGETYPE_PNG ? 8 : 12;
        $header = $type === IMAGETYPE_PNG ? 12 : 8;
        $total = strlen($source);
        while ($offset <= $total - $header) {
            $length = unpack($type === IMAGETYPE_PNG ? 'N' : 'V', substr($source, $offset + ($type === IMAGETYPE_PNG ? 0 : 4), 4))[1];
            if ($length > $total - $offset - $header) {
                break;
            }
            $tag = substr($source, $offset + ($type === IMAGETYPE_PNG ? 4 : 0), 4);
            if ($tag === ($type === IMAGETYPE_PNG ? 'eXIf' : 'EXIF')) {
                $payload = substr($source, $offset + 8, $length);
                if (str_starts_with($payload, "Exif\0\0")) {
                    $payload = substr($payload, 6);
                }
                $stream = fopen('php://memory', 'w+b');
                try {
                    fwrite($stream, $payload);
                    rewind($stream);
                    $metadata = @exif_read_data($stream, 'IFD0');

                    return (int) ($metadata['Orientation'] ?? 1);
                } finally {
                    fclose($stream);
                }
            }
            $offset += $header + $length + ($type === IMAGETYPE_WEBP ? ($length & 1) : 0);
        }

        return 1;
    }
}
