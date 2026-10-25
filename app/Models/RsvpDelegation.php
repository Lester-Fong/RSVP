<?php

namespace App\Models;

use Database\Factories\RsvpDelegationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsvpDelegation extends Model
{
    /** @use HasFactory<RsvpDelegationFactory> */
    use HasFactory;

    protected $guarded = ['*'];

    public function dependent(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'dependent_guest_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'representative_guest_id');
    }
}
