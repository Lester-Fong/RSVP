<?php

namespace App\Services;

use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\WebsiteRevisionMedia;
use App\Models\Wedding;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Internal persistence only: never switches publication or enables a reserved renderer. */
final class CreateWebsiteRevision
{
    public function __construct(private WebsiteDesignRegistry $designs, private CmsV2Contract $content, private WeddingImageAssetStorage $storage) {}

    /** @param list<string> $assetIds Server-derived references, never a customer-owned publication manifest. */
    public function create(Wedding $wedding, WebsiteRevision $revision, array $assetIds = []): WebsiteRevision
    {
        if ($revision->exists || $revision->getKey() !== null) {
            throw new LogicException('Only a new snapshot can receive media references.');
        }
        if (! array_is_list($assetIds)) {
            $this->invalidMedia();
        }
        foreach ($assetIds as $id) {
            if (! is_string($id) || ! Str::isUuid($id) || strtolower($id) !== $id) {
                $this->invalidMedia();
            }
        }
        if (count(array_unique($assetIds)) !== count($assetIds)) {
            $this->invalidMedia();
        }

        $connection = $wedding->getConnection();

        return $connection->transaction(function () use ($wedding, $revision, $assetIds, $connection): WebsiteRevision {
            $snapshot = new WebsiteRevision;
            foreach (array_keys($revision->getAttributes()) as $attribute) {
                $snapshot->setAttribute($attribute, $revision->getAttribute($attribute));
            }
            $locked = Wedding::on($connection->getName())->whereKey($wedding->id)->lockForUpdate()->firstOrFail();
            if ($revision->wedding_id !== null && (int) $revision->wedding_id !== $locked->id) {
                $this->invalidMedia();
            }
            $key = $revision->template_key ?? 'modern-classic';
            $version = $revision->template_version ?? 1;
            $this->designs->template($key, $version, $revision->schema_version);
            if ($revision->schema_version === 2) {
                $this->content->validate(['schema_version' => 2, 'template_key' => $key, 'template_version' => $version, 'content' => $revision->content]);
            } else {
                Validator::make(['content' => $revision->content], WebsiteDraft::contentRules())->validate();
            }
            $assets = $locked->mediaAssets()->whereIn('id', $assetIds)->orderBy('id')->lockForUpdate()->get();
            if ($assets->count() !== count($assetIds)) {
                $this->invalidMedia();
            }
            foreach ($assets as $asset) {
                $this->storage->validate($asset);
            }
            $snapshot->setConnection($connection->getName());
            $snapshot->forceFill(['wedding_id' => $locked->id, 'template_key' => $key, 'template_version' => $version]);
            $snapshot->save();
            foreach ($assets as $asset) {
                $reference = new WebsiteRevisionMedia;
                $reference->setConnection($connection->getName());
                $reference->forceFill(['website_revision_id' => $snapshot->id, 'wedding_media_asset_id' => $asset->id]);
                $reference->setRelation('revision', $snapshot);
                $reference->save();
            }

            return $snapshot;
        }, 3);
    }

    private function invalidMedia(): never
    {
        throw ValidationException::withMessages(['media' => 'Use distinct processed images owned by this wedding.']);
    }
}
