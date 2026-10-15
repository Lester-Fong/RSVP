<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
