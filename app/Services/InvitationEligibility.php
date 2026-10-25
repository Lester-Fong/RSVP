<?php

namespace App\Services;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InvitationEligibility
{
    /** The application binding is controlled by local/testing configuration and remains disabled by default. */
    public function __construct(public readonly bool $enabled = false) {}

    public function assertSchema(): void
    {
        abort_unless($this->enabled && Schema::hasColumn('guests', 'withdrawn_at'), 503);
    }

    public function active(Guest $guest): bool
    {
        abort_unless($this->enabled && array_key_exists('withdrawn_at', $guest->getAttributes()), 503);

        return $guest->getAttributes()['withdrawn_at'] === null;
    }

    public function constrain(Builder|Relation $query, string $state = 'active'): Builder|Relation
    {
        if (! $this->enabled) {
            return $query;
        }
        $this->assertSchema();

        return match ($state) {
            'withdrawn' => $query->whereNotNull('guests.withdrawn_at'),
            'all' => $query,
            default => $query->whereNull('guests.withdrawn_at'),
        };
    }

    public function guardDeletion(Guest $guest): void
    {
        if ($this->enabled) {
            $this->assertSchema();
            $this->active($guest);
            $stored = $guest->newQuery()->whereKey($guest->getKey())->firstOrFail(['id', 'withdrawn_at']);
            if (! $this->active($stored)) {
                throw ValidationException::withMessages(['guest' => 'This invitation is withdrawn. Keep the guest record, or explicitly reinstate the invitation before reviewing removal.']);
            }
        }
    }

    public function guardParentDeletion(Relation $guests): void
    {
        if ($this->enabled) {
            $this->assertSchema();
            if ($guests->whereNotNull('guests.withdrawn_at')->exists()) {
                throw ValidationException::withMessages(['guest' => 'Withdrawn invitations must be retained. This group or wedding cannot be permanently deleted.']);
            }
        }
    }
}
