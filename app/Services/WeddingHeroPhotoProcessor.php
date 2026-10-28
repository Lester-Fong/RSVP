<?php

namespace App\Services;

use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\Wedding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WeddingHeroPhotoProcessor
{
    public const DISK = 'wedding_photos';

    public function __construct(private WeddingImageProcessor $images) {}

    public function normalize(UploadedFile $file): string
    {
        return $this->images->normalize($file, 'hero_photo');
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
