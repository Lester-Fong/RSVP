<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Wedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_one_name',
        'partner_two_name',
        'wedding_date',
        'wedding_time',
        'ceremony_venue',
        'reception_venue',
        'public_slug',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'wedding_date' => 'date:Y-m-d',
            'publication_version' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function websiteDraft(): HasOne
    {
        return $this->hasOne(WebsiteDraft::class);
    }

    public function invitationGroups(): HasMany
    {
        return $this->hasMany(InvitationGroup::class);
    }

    public function guests(): HasManyThrough
    {
        return $this->hasManyThrough(Guest::class, InvitationGroup::class);
    }

    public function websiteRevisions(): HasMany
    {
        return $this->hasMany(WebsiteRevision::class);
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(WebsiteRevision::class, 'published_revision_id');
    }

    /** @return array<string, string|null> */
    public function presentationData(): array
    {
        return [
            'partner_one_name' => $this->partner_one_name,
            'partner_two_name' => $this->partner_two_name,
            'wedding_date' => $this->wedding_date?->format('Y-m-d'),
            'wedding_time' => $this->wedding_time ? substr($this->wedding_time, 0, 5) : null,
            'ceremony_venue' => $this->ceremony_venue,
            'reception_venue' => $this->reception_venue,
        ];
    }
}
