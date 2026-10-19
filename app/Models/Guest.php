<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        static::deleting(function (Guest $guest): void {
            if ($guest->rsvpResponse()->exists()) {
                throw ValidationException::withMessages(['guest' => 'This guest has an RSVP response and cannot be removed. You can rename them without losing their response.']);
            }
        });
    }

    public function rsvpResponse(): HasOne
    {
        return $this->hasOne(RsvpResponse::class);
    }

    public function invitationGroup(): BelongsTo
    {
        return $this->belongsTo(InvitationGroup::class);
    }
}
