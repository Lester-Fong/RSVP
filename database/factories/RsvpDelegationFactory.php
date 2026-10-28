<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\InvitationGroup;
use App\Models\RsvpDelegation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RsvpDelegation>
 */
class RsvpDelegationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dependent_guest_id' => Guest::factory()->for(InvitationGroup::factory()),
            'representative_guest_id' => null,
        ];
    }

    public function representedBy(Guest $representative): static
    {
        return $this->state(fn (): array => [
            'dependent_guest_id' => Guest::factory()->for($representative->invitationGroup),
            'representative_guest_id' => $representative->id,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['representative_guest_id' => null]);
    }
}
