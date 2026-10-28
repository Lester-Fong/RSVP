<?php

namespace App\Models;

use App\Services\WeddingImageAssetStorage;
use Database\Factories\WeddingMediaAssetFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class WeddingMediaAsset extends Model
{
    /** @use HasFactory<WeddingMediaAssetFactory> */
    use HasFactory, HasUuids;

    protected $guarded = ['*'];

    protected $hidden = ['wedding_id', 'disk', 'path', 'pivot'];

    protected function casts(): array
    {
        return ['byte_size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (WeddingMediaAsset $asset): void {
            app(WeddingImageAssetStorage::class)->validate($asset);
        });
        static::updating(function (): void {
            throw new LogicException('Processed media assets are immutable; replacement requires a new asset.');
        });
        static::deleting(function (WeddingMediaAsset $asset): void {
            if ($asset->revisions()->exists()) {
                throw new LogicException('Snapshot media must be retained.');
            }
        });
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function revisions(): BelongsToMany
    {
        return $this->belongsToMany(WebsiteRevision::class, 'website_revision_media')
            ->using(WebsiteRevisionMedia::class);
    }
}
