<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingMediaAsset;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Internal owner-only lookup. No URLs, HTTP routes or public media authority. */
final class WeddingImageResolver
{
    public function __construct(private CmsV2Contract $content, private WeddingImageAssetStorage $storage) {}

    /** @param array<string, mixed> $reference
     * @return array<string, int|string>
     */
    public function resolve(User $owner, Wedding $wedding, array $reference): array
    {
        $current = $this->authorize($owner, $wedding);
        $reference = $this->content->imageReference($reference);
        $asset = $this->owned($current, $reference['asset_id']);
        $this->read($asset);

        return $reference + $this->storage->metadata($asset);
    }

    /** Bounded verified bytes only; a future HTTP adapter must reauthorize and set private/no-store. */
    public function preview(User $owner, Wedding $wedding, string $assetId): string
    {
        return $this->read($this->owned($this->authorize($owner, $wedding), $assetId));
    }

    /**
     * Validate full saved source, including hidden/unsupported references; never persist a projection.
     * Future draft/publication writers must recheck inside their wedding-first transaction.
     *
     * @return array<string, array<string, int|string>> Unique asset metadata keyed by UUID.
     */
    public function validateDocument(User $owner, Wedding $wedding, CmsV2Document $document, bool $lock = false): array
    {
        $current = $this->authorize($owner, $wedding);
        $ids = [];
        foreach ($document->toArray()['content']['photo_slots'] ?? [] as $reference) {
            if ($reference !== null) {
                $ids[$reference['asset_id']] = $reference['asset_id'];
            }
        }
        foreach ($document->toArray()['content']['sections'] as $section) {
            $references = match ($section['type']) {
                'story' => array_column($section['data']['milestones'] ?? [], 'photo'),
                'gallery' => array_column($section['data']['items'], 'image'),
                default => [],
            };
            foreach ($references as $reference) {
                if ($reference !== null) {
                    $ids[$reference['asset_id']] = $reference['asset_id'];
                }
            }
        }
        $query = $current->mediaAssets()->whereIn('id', array_values($ids))->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $assets = $query->get()->keyBy('id');
        if ($assets->count() !== count($ids)) {
            $this->unavailable();
        }
        $result = [];
        foreach ($assets as $id => $asset) {
            $this->read($asset);
            $result[$id] = $this->storage->metadata($asset);
        }

        return $result;
    }

    private function authorize(User $owner, Wedding $wedding): Wedding
    {
        $current = Wedding::on($wedding->getConnectionName())->findOrFail($wedding->id);
        Gate::forUser($owner)->authorize('update', $current);

        return $current;
    }

    private function owned(Wedding $wedding, string $assetId): WeddingMediaAsset
    {
        if (! Str::isUuid($assetId) || strtolower($assetId) !== $assetId) {
            $this->unavailable();
        }
        $asset = $wedding->mediaAssets()->whereKey($assetId)->first();
        if (! $asset) {
            $this->unavailable();
        }

        return $asset;
    }

    private function read(WeddingMediaAsset $asset): string
    {
        try {
            return $this->storage->readVerified($asset);
        } catch (Throwable) {
            $this->unavailable();
        }
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages(['image' => 'This photo is unavailable for this wedding. Choose another photo.']);
    }
}
