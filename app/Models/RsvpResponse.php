<?php

namespace App\Models;

use Database\Factories\RsvpResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsvpResponse extends Model
{
    /** @use HasFactory<RsvpResponseFactory> */
    use HasFactory;

    protected $fillable = ['status', 'responded_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'immutable_datetime'];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
}
