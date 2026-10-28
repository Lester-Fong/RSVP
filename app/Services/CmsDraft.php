<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebsiteDraft;
use App\Models\Wedding;
use DateTimeZone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CmsDraft
{
    public function __construct(private CmsV2Contract $contract, private WeddingImageResolver $images) {}

    /** @return array{document: array<string, mixed>, draft_version: int, images: array<string, array<string, int|string>>, wedding: Wedding} */
    public function read(User $owner, Wedding $wedding): array
    {
        CmsEditorPreview::enforce();

        return $wedding->getConnection()->transaction(function () use ($owner, $wedding): array {
            $current = $wedding->newQuery()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('update', $current);
            $this->timezone($current);
            $draft = $current->websiteDraft()->lockForUpdate()->first();
            abort_if($draft && $draft->schema_version !== 2, 409);
            $source = $draft ? $this->document($draft) : CmsEditorPreview::document();
            $value = CmsV2Document::fromArray($source, $this->contract);

            return ['document' => $value->toArray(), 'draft_version' => $draft?->lock_version ?? 0,
                'images' => $this->images->validateDocument($owner, $current, $value, true), 'wedding' => $current];
        }, 3);
    }

    /** @param array<string, mixed> $source
     * @return array{draft_version: int, saved_at: string}
     */
    public function save(User $owner, Wedding $wedding, array $source, int $expected): array
    {
        CmsEditorPreview::enforce();

        return $wedding->getConnection()->transaction(function () use ($owner, $wedding, $source, $expected): array {
            $current = $wedding->newQuery()->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($owner)->authorize('update', $current);
            $this->timezone($current);
            $draft = $current->websiteDraft()->lockForUpdate()->first();
            abort_if($draft && ($draft->schema_version !== 2 || $draft->template_key !== ($source['template_key'] ?? null)
                || $draft->template_version !== ($source['template_version'] ?? null)), 409);
            if (($draft?->lock_version ?? 0) !== $expected || $expected < 0 || $expected >= 4294967295) {
                throw ValidationException::withMessages([
                    'draft_version' => 'This draft changed in another window, or its save version is unavailable. Your edits remain here. Review the saved draft before reloading.',
                ]);
            }
            $value = CmsV2Document::fromArray($source, $this->contract);
            $this->images->validateDocument($owner, $current, $value, true);
            $draft ??= $current->websiteDraft()->make();
            $draft->fill(['schema_version' => 2, 'template_key' => $value->templateKey, 'template_version' => $value->templateVersion,
                'content' => $value->toArray()['content'], 'lock_version' => $expected + 1]);
            $draft->save();

            return ['draft_version' => $draft->lock_version, 'saved_at' => $draft->updated_at->toIso8601String()];
        }, 3);
    }

    /** @return array<string, mixed> */
    private function document(WebsiteDraft $draft): array
    {
        return ['schema_version' => $draft->schema_version, 'template_key' => $draft->template_key,
            'template_version' => $draft->template_version, 'content' => $draft->content];
    }

    private function timezone(Wedding $wedding): void
    {
        abort_unless(in_array($wedding->getAttribute('timezone'), DateTimeZone::listIdentifiers(), true), 503);
    }
}
