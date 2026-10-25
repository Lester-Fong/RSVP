<?php

namespace App\Models;

use App\Services\InvitationEligibility;
use App\Services\RsvpMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Wedding extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Wedding $wedding): void {
            app(InvitationEligibility::class)->guardParentDeletion($wedding->guests());
            if (app(RsvpMode::class)->personal() && ($wedding->invitationGroups()->where('invitation_link_version', '>', 0)->exists()
                || $wedding->guests()->where(fn ($guests) => $guests->has('rsvpResponse')->orHas('invitationLink')->orHas('rsvpDelegation')->orHas('representedDelegations'))->exists())) {
                throw ValidationException::withMessages(['wedding' => 'This wedding contains retained invitations or RSVP records and cannot be permanently deleted.']);
            }
        });
    }

    protected $fillable = [
        'partner_one_name',
        'partner_two_name',
        'wedding_date',
        'wedding_time',
        'timezone',
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

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(WeddingMediaAsset::class);
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
