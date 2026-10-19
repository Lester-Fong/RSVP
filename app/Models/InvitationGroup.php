<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class InvitationGroup extends Model
{
    use HasFactory;

    public const MAX_GUESTS = 100;

    protected $fillable = ['label', 'contact_email', 'contact_phone'];

    protected $hidden = ['invitation_token_hash', 'invitation_token'];

    protected $attributes = ['invitation_link_version' => 0, 'rsvp_version' => 0];

    protected function casts(): array
    {
        return ['invitation_token' => 'encrypted', 'invitation_link_version' => 'integer', 'rsvp_version' => 'integer'];
    }

    public function linkStatus(): string
    {
        if ($this->invitation_link_version === 0) {
            return 'not_generated';
        }

        return $this->invitation_token_hash !== null && ($this->getAttributes()['invitation_token'] ?? null) !== null ? 'active' : 'revoked';
    }

    /** Compare ciphertext directly so rotation can replace an unreadable previous token. */
    public function originalIsEquivalent(mixed $key): bool
    {
        return $key === 'invitation_token'
            ? ($this->getAttributes()[$key] ?? null) === $this->getRawOriginal($key)
            : parent::originalIsEquivalent($key);
    }

    protected static function booted(): void
    {
        static::deleting(function (InvitationGroup $group): void {
            if ($group->invitation_link_version > 0) {
                throw ValidationException::withMessages(['invitation_link' => 'This group has had an invitation link and cannot be deleted. Revoke its link to stop access instead.']);
            }
        });
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }
}
