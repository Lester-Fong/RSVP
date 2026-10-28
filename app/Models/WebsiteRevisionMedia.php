<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;
use LogicException;

class WebsiteRevisionMedia extends Pivot
{
    protected $table = 'website_revision_media';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['website_revision_id', 'wedding_media_asset_id'];

    protected static function booted(): void
    {
        static::creating(function (WebsiteRevisionMedia $reference): void {
            if (! $reference->relationLoaded('revision') || ! $reference->revision->wasRecentlyCreated
                || $reference->getConnection()->transactionLevel() === 0) {
                throw new LogicException('Media references must be created atomically with a new snapshot.');
            }
            $revisionWedding = WebsiteRevision::on($reference->getConnectionName())->whereKey($reference->website_revision_id)->value('wedding_id');
            $assetWedding = WeddingMediaAsset::on($reference->getConnectionName())->whereKey($reference->wedding_media_asset_id)->value('wedding_id');
            if ($revisionWedding === null || $assetWedding === null || (int) $revisionWedding !== (int) $assetWedding
                || $reference->revision->id !== $reference->website_revision_id) {
                throw ValidationException::withMessages(['media' => 'Every snapshot image must belong to the same wedding.']);
            }
        });
        static::updating(function (): void {
            throw new LogicException('Snapshot media references are immutable.');
        });
        static::deleting(function (): void {
            throw new LogicException('Snapshot media references must be retained.');
        });
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(WebsiteRevision::class, 'website_revision_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(WeddingMediaAsset::class, 'wedding_media_asset_id');
    }
}
