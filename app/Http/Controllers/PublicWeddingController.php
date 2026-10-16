<?php

namespace App\Http\Controllers;

use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\Wedding;
use App\Services\WeddingHeroPhotoProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicWeddingController extends Controller
{
    public function show(string $slug): Response
    {
        [, $revision] = $this->published($slug);
        $facts = $revision->wedding_facts;
        $customization = $revision->content;
        if (! $customization['sections']['ceremony']) {
            $facts['ceremony_venue'] = null;
        }
        if (! $customization['sections']['reception']) {
            $facts['reception_venue'] = null;
        }
        if (! $customization['sections']['closing']) {
            $customization['closing_message'] = '';
        }
        $url = route('public.weddings.show', ['slug' => $slug]);
        $photoUrl = $revision->hero_photo_path ? route('public.weddings.photo', ['slug' => $slug, 'photo' => pathinfo($revision->hero_photo_path, PATHINFO_FILENAME)]) : null;
        $props = ['wedding' => $facts, 'customization' => $customization, 'heroPhoto' => $photoUrl ? ['url' => $photoUrl] : null];

        return response()->view('public.wedding', [
            'props' => $props, 'wedding' => $facts, 'customization' => $customization, 'photoUrl' => $photoUrl, 'url' => $url,
            'title' => $facts['partner_one_name'].' & '.$facts['partner_two_name'].' — Wedding invitation',
            'description' => Str::limit(preg_replace('/\s+/u', ' ', $revision->content['introduction_message']), 160),
            'formattedDate' => CarbonImmutable::createFromFormat('!Y-m-d', $facts['wedding_date'])->locale('en')->isoFormat('dddd, MMMM D, YYYY'),
            'formattedTime' => $facts['wedding_time'] ? CarbonImmutable::createFromFormat('!H:i', $facts['wedding_time'])->format('g:i A') : null,
        ], 200, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow, noarchive']);
    }

    public function photo(string $slug, string $photo, WeddingHeroPhotoProcessor $photos): StreamedResponse
    {
        [$wedding, $revision] = $this->published($slug);
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

    /** @return array{Wedding, WebsiteRevision} */
    private function published(string $slug): array
    {
        $wedding = Wedding::query()->where('public_slug', $slug)->firstOrFail();
        $revision = $wedding->websiteRevisions()->whereKey($wedding->published_revision_id)->first();
        abort_unless($revision && $revision->schema_version === WebsiteDraft::SCHEMA_VERSION, 404);

        return [$wedding, $revision];
    }
}
