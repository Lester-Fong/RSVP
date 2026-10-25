<?php

namespace App\Models;

use App\Services\InvitationEligibility;
use App\Services\PersonalRsvpTransaction;
use App\Services\RsvpMode;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    protected function casts(): array
    {
        return ['rsvp_version' => 'integer', 'rsvp_access_version' => 'integer', 'withdrawn_at' => 'immutable_datetime'];
    }

    #[Scope]
    protected function activeInvitation(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('withdrawn_at'));
    }

    #[Scope]
    protected function withdrawnInvitation(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('withdrawn_at'));
    }

    public function invitationLink(): HasOne
    {
        return $this->hasOne(GuestInvitationLink::class);
    }

    public function rsvpDelegation(): HasOne
    {
        return $this->hasOne(RsvpDelegation::class, 'dependent_guest_id');
    }

    public function representedDelegations(): HasMany
    {
        return $this->hasMany(RsvpDelegation::class, 'representative_guest_id');
    }

    protected static function booted(): void
    {
        static::updating(function (Guest $guest): void {
            if (app(RsvpMode::class)->personal()) {
                if ($guest->isDirty('invitation_group_id')) {
                    throw ValidationException::withMessages(['guest' => 'Guests cannot be moved between invitation groups.']);
                }
                if ($guest->isDirty('name')) {
                    if ($guest->getRawOriginal('rsvp_version') === null) {
                        throw ValidationException::withMessages(['guest' => 'Reload this guest before changing their name.']);
                    }
                    $guest->rsvp_version = app(PersonalRsvpTransaction::class)->nextVersion((int) $guest->getRawOriginal('rsvp_version'));
                }
            }
        });
        static::deleting(function (Guest $guest): void {
            app(InvitationEligibility::class)->guardDeletion($guest);
            if ($guest->rsvpResponse()->exists()) {
                throw ValidationException::withMessages(['guest' => 'This guest has an RSVP response and cannot be removed. You can rename them without losing their response.']);
            }
            if (app(RsvpMode::class)->personal() && ($guest->invitationLink()->exists() || $guest->rsvpDelegation()->exists() || $guest->representedDelegations()->exists())) {
                throw ValidationException::withMessages(['guest' => 'This guest has had personal RSVP access or representation and must stay on your guest list. Revoke their access to stop responses; this does not remove them from invited totals.']);
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
