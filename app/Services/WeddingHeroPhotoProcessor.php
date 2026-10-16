<?php

namespace App\Services;

use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\Wedding;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class WeddingHeroPhotoProcessor
{
    public const DISK = 'wedding_photos';

    public function normalize(UploadedFile $file): string
    {
        if (! extension_loaded('gd') || ! function_exists('exif_read_data') || ! function_exists('imagewebp')
            || ! (imagetypes() & IMG_JPG) || ! (imagetypes() & IMG_PNG) || ! (imagetypes() & IMG_WEBP)
            || (ini_get('memory_limit') !== '-1' && ini_parse_quantity(ini_get('memory_limit')) < 256 * 1024 * 1024)) {
            throw ValidationException::withMessages(['hero_photo' => 'Photo uploads are temporarily unavailable. Please contact support.']);
        }

        $size = @getimagesize($file->getPathname());
        if (! $size || ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $file->getSize() > 5 * 1024 * 1024 || max($size[0], $size[1]) > 6000 || $size[0] * $size[1] > 12000000) {
            throw ValidationException::withMessages(['hero_photo' => 'Choose a valid JPEG, PNG, or WebP photo up to 5 MiB and 12 million pixels.']);
        }

        $image = @imagecreatefromstring(file_get_contents($file->getPathname()));
        if (! $image) {
            throw ValidationException::withMessages(['hero_photo' => 'This photo could not be read. Please choose another image.']);
        }

        if ($size[2] === IMAGETYPE_JPEG) {
            $metadata = @exif_read_data($file->getPathname(), 'IFD0');
            $image = $this->orient($image, (int) ($metadata['Orientation'] ?? 1));
        }

        $scale = min(1, 1600 / max(imagesx($image), imagesy($image)));
        $output = imagecreatetruecolor(max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), imagesx($image), imagesy($image));
        unset($image);

        ob_start();
        try {
            $encoded = imagewebp($output, null, 82);
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        if (! $encoded || ! $bytes) {
            throw ValidationException::withMessages(['hero_photo' => 'This photo could not be processed. Please choose another image.']);
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

    public function validPath(string $path, int $accountId, int $weddingId): bool
    {
        return preg_match('~\Aaccounts/'.$accountId.'/weddings/'.$weddingId.'/hero/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.webp\z~D', $path) === 1;
    }

    /** Recheck under the same parent lock used by saves; failures leave an orphan for a later retry. */
    public function deleteIfUnreferenced(int $accountId, int $weddingId, string $disk, string $path, ?int $olderThan = null, bool $delete = true): bool
    {
        if ($disk !== self::DISK || ! $this->validPath($path, $accountId, $weddingId)) {
            return false;
        }

        try {
            return DB::transaction(function () use ($weddingId, $disk, $path, $olderThan, $delete): bool {
                Wedding::query()->useWritePdo()->whereKey($weddingId)->lockForUpdate()->first();
                $draft = WebsiteDraft::query()->useWritePdo()->where('wedding_id', $weddingId)->first();
                if (($draft && $draft->schema_version !== WebsiteDraft::SCHEMA_VERSION)
                    || WebsiteDraft::query()->useWritePdo()->where('hero_photo_disk', $disk)->where('hero_photo_path', $path)->exists()
                    || WebsiteRevision::query()->useWritePdo()->where('hero_photo_disk', $disk)->where('hero_photo_path', $path)->exists()) {
                    return false;
                }

                $storage = Storage::disk($disk);
                if (! $storage->exists($path) || ($olderThan !== null && $storage->lastModified($path) >= $olderThan)) {
                    return false;
                }

                return ! $delete || $storage->delete($path);
            });
        } catch (Throwable) {
            Log::warning('Wedding photo cleanup deferred.', ['wedding_id' => $weddingId]);

            return false;
        }
    }
}
