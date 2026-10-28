<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingMediaAsset;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Throwable;

/** Inactive owner action; owns its transaction and never assigns/publishes a draft. */
final class UploadWeddingImage
{
    public function __construct(private WeddingImageProcessor $images, private WeddingImageAssetStorage $storage) {}

    /** @return array{id: string, kind: string, mime_type: string, byte_size: int, width: int, height: int} */
    public function upload(User $owner, Wedding $wedding, UploadedFile $file): array
    {
        $connection = $wedding->getConnection();
        $current = Wedding::on($connection->getName())->findOrFail($wedding->id);
        Gate::forUser($owner)->authorize('update', $current);
        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('Image uploads must own their transaction; do not wrap them in another transaction.');
        }
        Validator::make(['image' => $file], [
            'image' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
        ])->validate();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $extensions = match ($mime) {
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            default => [],
        };
        if (! in_array(strtolower($file->getClientOriginalExtension()), $extensions, true)) {
            throw ValidationException::withMessages(['image' => 'Choose a JPEG, PNG or WebP photo whose file extension matches its contents.']);
        }
        $bytes = $this->images->normalize($file);
        $size = getimagesizefromstring($bytes);
        $id = (string) Str::uuid();
        $path = WeddingImageAssetStorage::path($current->id, $id);
        $ownsFile = false;
        try {
            $connection->beginTransaction();
            $locked = Wedding::on($connection->getName())->whereKey($current->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('update', $locked);
            $asset = new WeddingMediaAsset;
            $asset->setConnection($connection->getName());
            $asset->forceFill(['id' => $id, 'wedding_id' => $locked->id, 'kind' => 'image',
                'disk' => WeddingImageAssetStorage::DISK, 'path' => $path,
                'mime_type' => WeddingImageAssetStorage::MIME_TYPE, 'byte_size' => strlen($bytes),
                'width' => $size[0], 'height' => $size[1]]);
            // Reserve the unique database identity before writing; every writer holds this wedding lock.
            $asset->save();
            $disk = Storage::disk(WeddingImageAssetStorage::DISK);
            if ($disk->exists($path)) {
                throw new RuntimeException('A private image slot already exists.');
            }
            $ownsFile = true;
            if (! $disk->put($path, $bytes, ['visibility' => 'private']) || $this->storage->readVerified($asset) !== $bytes) {
                throw new RuntimeException('Private image storage did not complete.');
            }

            $result = $this->storage->metadata($asset);
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($connection->transactionLevel() > 0) {
                try {
                    $connection->rollBack();
                } catch (Throwable) {
                    Log::warning('Wedding image rollback could not be confirmed.', ['wedding_id' => $current->id]);
                }
            }
            if ($ownsFile) {
                $this->compensate($current, $id, $path);
            }
            if ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException) {
                throw $exception;
            }
            Log::warning('Wedding image upload did not complete; check saved assets before retry.', ['wedding_id' => $current->id]);
            throw ValidationException::withMessages(['image' => 'Your photo could not be saved. Reload your photos before trying again.']);
        }
    }

    /** Delete only this failed operation's file when the primary database proves no asset was committed. */
    private function compensate(Wedding $wedding, string $id, string $path): void
    {
        $connection = $wedding->getConnection();
        try {
            if ($connection->transactionLevel() !== 0) {
                throw new RuntimeException('Upload transaction has not finished.');
            }
            $connection->transaction(function () use ($connection, $wedding, $id, $path): void {
                $locked = Wedding::on($connection->getName())->useWritePdo()->whereKey($wedding->id)->lockForUpdate()->first();
                if (! $locked || WeddingMediaAsset::on($connection->getName())->useWritePdo()->whereKey($id)->exists()) {
                    return;
                }
                $disk = Storage::disk(WeddingImageAssetStorage::DISK);
                if ($disk->exists($path) && ! $disk->delete($path)) {
                    throw new RuntimeException('Private upload cleanup must be retried.');
                }
            }, 1);
        } catch (Throwable) {
            Log::warning('Wedding image upload cleanup deferred.', ['wedding_id' => $wedding->id, 'asset_id' => $id]);
        }
    }
}
