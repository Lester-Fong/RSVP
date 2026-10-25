<?php

namespace App\Http\Controllers;

use App\Exceptions\UnsupportedWebsiteDraftVersion;
use App\Http\Requests\UpdateWeddingInvitationRequest;
use App\Models\Account;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use App\Services\CmsEditorPreview;
use App\Services\WeddingHeroPhotoProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WeddingInvitationController extends Controller
{
    public function edit(Account $account, Wedding $wedding): Response|RedirectResponse
    {
        $this->authorize('update', $wedding);

        $draft = $wedding->websiteDraft;
        if (CmsEditorPreview::shouldOpen($wedding)) {
            return to_route('accounts.weddings.cms-preview.show', [$account, $wedding]);
        }
        $draft?->ensureSupportedSchema();

        return Inertia::render('weddings/InvitationEditor', [
            'account' => ['id' => $account->id, 'name' => $account->name],
            'weddingId' => $wedding->id,
            'wedding' => $wedding->presentationData(),
            'customization' => $draft?->content ?? WebsiteDraft::defaultContent(),
            'draftVersion' => $draft?->lock_version ?? 0,
            'heroPhoto' => $draft?->heroPhoto(),
        ]);
    }

    public function update(UpdateWeddingInvitationRequest $request, Account $account, Wedding $wedding, WeddingHeroPhotoProcessor $photos): RedirectResponse
    {
        $attributes = $request->validated();
        $wedding->websiteDraft?->ensureSupportedSchema();
        $action = $attributes['hero_photo_action'] ?? 'keep';
        $bytes = $action === 'replace' ? $photos->normalize($request->file('hero_photo')) : null;
        $newPath = null;
        $oldDisk = null;
        $oldPath = null;

        try {
            DB::transaction(function () use ($wedding, $attributes, $action, $bytes, &$newPath, &$oldDisk, &$oldPath) {
                $wedding = Wedding::query()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
                $draft = $wedding->websiteDraft;

                $draft?->ensureSupportedSchema();

                if (($draft?->lock_version ?? 0) !== (int) $attributes['draft_version']) {
                    throw ValidationException::withMessages([
                        'draft_version' => 'This draft was saved in another window. Copy your changes, then reload before saving again.',
                    ]);
                }

                $photoAttributes = [];
                if ($action !== 'keep') {
                    $oldDisk = $draft?->hero_photo_disk;
                    $oldPath = $draft?->hero_photo_path;
                    if ($action === 'replace') {
                        $newPath = 'accounts/'.$wedding->account_id.'/weddings/'.$wedding->id.'/hero/'.Str::uuid().'.webp';
                        if (! Storage::disk(WeddingHeroPhotoProcessor::DISK)->put($newPath, $bytes, ['visibility' => 'private'])) {
                            throw new \RuntimeException('Wedding photo storage failed.');
                        }
                    }
                    $photoAttributes = ['hero_photo_disk' => $newPath ? WeddingHeroPhotoProcessor::DISK : null, 'hero_photo_path' => $newPath];
                }

                $wedding->websiteDraft()->updateOrCreate([], [
                    'schema_version' => WebsiteDraft::SCHEMA_VERSION,
                    'lock_version' => (int) $attributes['draft_version'] + 1,
                    'content' => $attributes['content'],
                    ...$photoAttributes,
                ]);
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $photos->deleteIfUnreferenced($account->id, $wedding->id, WeddingHeroPhotoProcessor::DISK, $newPath);
            }
            if ($exception instanceof ValidationException || $exception instanceof UnsupportedWebsiteDraftVersion || $action === 'keep') {
                throw $exception;
            }
            Log::warning('Wedding photo save failed; saved references must be checked before retry.', ['wedding_id' => $wedding->id]);
            throw ValidationException::withMessages(['hero_photo' => 'Your photo could not be saved. Reload to check the saved draft before trying again.']);
        }

        if ($oldDisk !== null && $oldPath !== null) {
            $photos->deleteIfUnreferenced($account->id, $wedding->id, $oldDisk, $oldPath);
        }

        return to_route('accounts.weddings.invitation.edit', [$account, $wedding]);
    }

    public function heroPhoto(Account $account, Wedding $wedding, string $photo, WeddingHeroPhotoProcessor $photos): StreamedResponse
    {
        $this->authorize('view', $wedding);
        $draft = $wedding->websiteDraft;
        $draft?->ensureSupportedSchema();
        $path = $draft?->hero_photo_path;
        abort_unless($path && $draft->hero_photo_disk === WeddingHeroPhotoProcessor::DISK
            && $photos->validPath($path, $account->id, $wedding->id)
            && pathinfo($path, PATHINFO_FILENAME) === $photo, 404);
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
        }, 200, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
