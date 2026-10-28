<?php

namespace App\Models;

use Database\Factories\WebsiteRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class WebsiteRevision extends Model
{
    /** @use HasFactory<WebsiteRevisionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['hero_photo_disk', 'hero_photo_path', 'fingerprint'];

    protected function casts(): array
    {
        return ['schema_version' => 'integer', 'template_version' => 'integer', 'revision_number' => 'integer', 'wedding_facts' => 'array', 'content' => 'array', 'published_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Published snapshots are immutable.');
        });
        static::deleting(function (): void {
            throw new LogicException('Published snapshots must be retained.');
        });
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function mediaAssets(): BelongsToMany
    {
        return $this->belongsToMany(WeddingMediaAsset::class, 'website_revision_media')
            ->using(WebsiteRevisionMedia::class);
    }
}
