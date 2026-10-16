<?php

namespace App\Services;

use App\Models\WebsiteDraft;
use App\Models\Wedding;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class WeddingPublication
{
    public function __construct(private WeddingHeroPhotoProcessor $photos) {}

    public function fingerprint(Wedding $wedding, ?WebsiteDraft $draft): string
    {
        return hash('sha256', json_encode(Arr::sortRecursive([
            'wedding' => $wedding->presentationData(), 'schema' => $draft?->schema_version,
            'content' => $draft?->content, 'disk' => $draft?->hero_photo_disk, 'path' => $draft?->hero_photo_path,
        ]), JSON_THROW_ON_ERROR));
    }

    /** @return array<string, bool|int|string|null> */
    public function summary(Wedding $wedding): array
    {
        $draft = $wedding->websiteDraft;
        $last = $wedding->websiteRevisions()->latest('revision_number')->first();
        $published = $wedding->websiteRevisions()->whereKey($wedding->published_revision_id)->first();
        $token = $this->fingerprint($wedding, $draft);

        return [
            'published' => $published !== null, 'draftSaved' => $draft !== null,
            'supportedDraft' => $draft !== null && $draft->schema_version === WebsiteDraft::SCHEMA_VERSION,
            'hasChanges' => $published === null || ! hash_equals($published->fingerprint, $token),
            'draftVersion' => $draft?->lock_version ?? 0, 'publicationVersion' => $wedding->publication_version,
            'snapshotToken' => $token, 'publicUrl' => route('public.weddings.show', ['slug' => $wedding->public_slug]),
            'publishedAt' => $last?->published_at->toIso8601String(),
        ];
    }

    /** @param array{draft_version: int|string, publication_version: int|string, snapshot_token: string} $expected */
    public function publish(Wedding $wedding, array $expected): void
    {
        DB::transaction(function () use ($wedding, $expected): void {
            $locked = Wedding::query()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            $this->checkVersion($locked, (int) $expected['publication_version']);
            $draft = $locked->websiteDraft;
            if (! $draft) {
                throw ValidationException::withMessages(['publication' => 'Save your invitation draft before publishing.']);
            }
            $draft->ensureSupportedSchema();
            $fingerprint = $this->fingerprint($locked, $draft);
            if ($draft->lock_version !== (int) $expected['draft_version'] || ! hash_equals($fingerprint, $expected['snapshot_token'])) {
                throw ValidationException::withMessages(['publication' => 'The saved draft or wedding details changed. Reload and review them before publishing.']);
            }

            $facts = $locked->presentationData();
            Validator::make(['wedding' => $facts, 'content' => $draft->content], [
                'wedding.partner_one_name' => ['required', 'string', 'max:255'],
                'wedding.partner_two_name' => ['required', 'string', 'max:255'],
                'wedding.wedding_date' => ['required', 'date_format:Y-m-d'],
                'wedding.wedding_time' => ['nullable', 'date_format:H:i'],
                'wedding.ceremony_venue' => ['nullable', 'string', 'max:500'],
                'wedding.reception_venue' => ['nullable', 'string', 'max:500'],
                ...WebsiteDraft::contentRules(),
            ], ['wedding.wedding_date.required' => 'Add a wedding date in Edit wedding before publishing.'])->validate();
            $this->validatePhoto($locked, $draft);

            $current = $locked->websiteRevisions()->whereKey($locked->published_revision_id)->first();
            if ($current && hash_equals($current->fingerprint, $fingerprint)) {
                return;
            }

            $revision = $locked->websiteRevisions()->create([
                'revision_number' => ($locked->websiteRevisions()->max('revision_number') ?? 0) + 1,
                'schema_version' => WebsiteDraft::SCHEMA_VERSION, 'wedding_facts' => $facts, 'content' => $draft->content,
                'hero_photo_disk' => $draft->hero_photo_disk, 'hero_photo_path' => $draft->hero_photo_path,
                'fingerprint' => $fingerprint, 'published_at' => now(),
            ]);
            $locked->published_revision_id = $revision->id;
            $locked->publication_version++;
            $locked->save();
        });
    }

    public function unpublish(Wedding $wedding, int $expectedVersion): void
    {
        DB::transaction(function () use ($wedding, $expectedVersion): void {
            $locked = Wedding::query()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            $this->checkVersion($locked, $expectedVersion);
            if ($locked->published_revision_id !== null) {
                $locked->published_revision_id = null;
                $locked->publication_version++;
                $locked->save();
            }
        });
    }

    private function checkVersion(Wedding $wedding, int $expectedVersion): void
    {
        if ($wedding->publication_version !== $expectedVersion) {
            throw ValidationException::withMessages(['publication' => 'Publication changed in another window. Reload before trying again.']);
        }
    }

    private function validatePhoto(Wedding $wedding, WebsiteDraft $draft): void
    {
        if ($draft->hero_photo_disk === null && $draft->hero_photo_path === null) {
            return;
        }
        $valid = false;
        if ($draft->hero_photo_disk === WeddingHeroPhotoProcessor::DISK && is_string($draft->hero_photo_path)
            && $this->photos->validPath($draft->hero_photo_path, $wedding->account_id, $wedding->id)) {
            try {
                $disk = Storage::disk(WeddingHeroPhotoProcessor::DISK);
                if ($disk->exists($draft->hero_photo_path) && $disk->size($draft->hero_photo_path) <= 5 * 1024 * 1024) {
                    $size = @getimagesizefromstring($disk->get($draft->hero_photo_path));
                    $valid = $size && $size[2] === IMAGETYPE_WEBP && max($size[0], $size[1]) <= 1600;
                }
            } catch (Throwable) {
                $valid = false;
            }
        }
        if (! $valid) {
            throw ValidationException::withMessages(['publication' => 'Your saved photo is unavailable or invalid. Replace or remove it, save the draft, and try again.']);
        }
    }
}
