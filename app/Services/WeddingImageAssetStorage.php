<?php

namespace App\Services;

use App\Models\WeddingMediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Code-owned private image paths, metadata and verified byte reads. */
final class WeddingImageAssetStorage
{
    public const DISK = 'wedding_media';

    public const MIME_TYPE = 'image/webp';

    public static function path(int $weddingId, string $assetId): string
    {
        return 'weddings/'.$weddingId.'/images/'.$assetId.'.webp';
    }

    public function validate(WeddingMediaAsset $asset): void
    {
        Validator::make($asset->getAttributes(), [
            'id' => ['required', 'string', 'uuid', 'lowercase'],
            'wedding_id' => ['required', 'integer', 'min:1'],
            'kind' => ['required', 'in:image'],
            'disk' => ['required', 'in:'.self::DISK],
            'mime_type' => ['required', 'in:'.self::MIME_TYPE],
            'byte_size' => ['required', 'integer', 'min:1', 'max:5242880'],
            'width' => ['required', 'integer', 'min:1', 'max:1600'],
            'height' => ['required', 'integer', 'min:1', 'max:1600'],
        ])->validate();

        if ($asset->path !== self::path($asset->wedding_id, $asset->id)) {
            throw ValidationException::withMessages(['media' => 'Use the code-owned private image storage reference.']);
        }
    }

    /** @return array{id: string, kind: string, mime_type: string, byte_size: int, width: int, height: int} */
    public function metadata(WeddingMediaAsset $asset): array
    {
        return ['id' => $asset->id, 'kind' => $asset->kind, 'mime_type' => $asset->mime_type,
            'byte_size' => $asset->byte_size, 'width' => $asset->width, 'height' => $asset->height];
    }

    /** Internal read only: callers must authorize the wedding before using this operation. */
    public function readVerified(WeddingMediaAsset $asset): string
    {
        $this->validate($asset);
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($asset->path) || $disk->size($asset->path) !== $asset->byte_size) {
            $this->unavailable();
        }
        $bytes = $disk->get($asset->path);
        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[2] !== IMAGETYPE_WEBP || strlen($bytes) !== $asset->byte_size
            || $size[0] !== $asset->width || $size[1] !== $asset->height || ! @imagecreatefromstring($bytes)) {
            $this->unavailable();
        }

        return $bytes;
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages(['image' => 'This photo is unavailable. Choose another photo or upload it again.']);
    }
}
