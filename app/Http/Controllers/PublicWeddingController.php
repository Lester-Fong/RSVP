<?php

namespace App\Http\Controllers;

use App\Models\Wedding;
use App\Services\PublishedWedding;
use App\Services\WeddingHeroPhotoProcessor;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicWeddingController extends Controller
{
    public function show(string $slug, PublishedWedding $published): Response
    {
        return $published->render(Wedding::query()->where('public_slug', $slug)->firstOrFail());
    }

    public function photo(string $slug, string $photo, WeddingHeroPhotoProcessor $photos, PublishedWedding $published): StreamedResponse
    {
        $wedding = Wedding::query()->where('public_slug', $slug)->firstOrFail();
        $revision = $published->revision($wedding);
        $path = $revision->hero_photo_path;
        abort_unless($revision->hero_photo_disk === WeddingHeroPhotoProcessor::DISK && is_string($path)
            && $photos->validPath($path, $wedding->account_id, $wedding->id) && pathinfo($path, PATHINFO_FILENAME) === $photo, 404);
        $disk = Storage::disk(WeddingHeroPhotoProcessor::DISK);
        abort_unless($disk->exists($path), 404);
        try {
            $stream = $disk->readStream($path);
        } catch (UnableToReadFile) {
            abort(404);
        }
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow, noarchive']);
    }
}
