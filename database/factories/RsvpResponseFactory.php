<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\RsvpResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RsvpResponse>
 */
class RsvpResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory()->for(InvitationGroup::factory()),
            'status' => 'attending',
            'responded_at' => now(),
        ];
    }
}
