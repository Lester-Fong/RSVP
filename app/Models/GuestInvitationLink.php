<?php

namespace App\Models;

use Database\Factories\GuestInvitationLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestInvitationLink extends Model
{
    /** @use HasFactory<GuestInvitationLinkFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    protected $hidden = ['token_hash', 'token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'version' => 'integer'];
    }

    /** Compare ciphertext so explicit rotation can replace an unreadable previous credential. */
    public function originalIsEquivalent(mixed $key): bool
    {
        return $key === 'token'
            ? ($this->getAttributes()[$key] ?? null) === $this->getRawOriginal($key)
            : parent::originalIsEquivalent($key);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
}
