<?php

namespace App\Models;

use App\Exceptions\UnsupportedWebsiteDraftVersion;
use Database\Factories\WebsiteDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteDraft extends Model
{
    /** @use HasFactory<WebsiteDraftFactory> */
    use HasFactory;

    public const SCHEMA_VERSION = 1;

    protected $fillable = ['schema_version', 'lock_version', 'content', 'hero_photo_disk', 'hero_photo_path'];

    protected $hidden = ['hero_photo_disk', 'hero_photo_path'];

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'lock_version' => 'integer',
            'content' => 'array',
        ];
    }

    /**
     * @return array{introduction_message: string, closing_message: string, sections: array{ceremony: bool, reception: bool, closing: bool}}
     */
    public static function defaultContent(): array
    {
        return [
            'introduction_message' => 'Joyfully invite you to celebrate their wedding',
            'closing_message' => 'We look forward to celebrating with you',
            'sections' => ['ceremony' => true, 'reception' => true, 'closing' => true],
        ];
    }

    /** @return array<string, array<int, string>> */
    public static function contentRules(): array
    {
        return [
            'content' => ['required', 'array:introduction_message,closing_message,sections'],
            'content.introduction_message' => ['required', 'string', 'max:500', 'not_regex:/[<>]/u'],
            'content.closing_message' => ['required', 'string', 'max:500', 'not_regex:/[<>]/u'],
            'content.sections' => ['required', 'array:ceremony,reception,closing'],
            'content.sections.ceremony' => ['required', 'boolean:strict'],
            'content.sections.reception' => ['required', 'boolean:strict'],
            'content.sections.closing' => ['required', 'boolean:strict'],
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function ensureSupportedSchema(): void
    {
        if ($this->schema_version !== self::SCHEMA_VERSION) {
            throw new UnsupportedWebsiteDraftVersion;
        }
    }

    /** @return array{url: string}|null */
    public function heroPhoto(): ?array
    {
        $this->ensureSupportedSchema();

        if (! $this->hero_photo_path || $this->hero_photo_disk !== 'wedding_photos') {
            return null;
        }

        return ['url' => route('accounts.weddings.invitation.hero-photo', [
            $this->wedding->account_id, $this->wedding_id, pathinfo($this->hero_photo_path, PATHINFO_FILENAME),
        ])];
    }
}
