<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\GuestInvitationLink;
use App\Models\InvitationGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestInvitationLink>
 */
class GuestInvitationLinkFactory extends Factory
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
            'token_hash' => null,
            'token' => null,
            'version' => 1,
        ];
    }

    public function withSyntheticToken(string $token = 'synthetic-fixture-only-not-a-bearer-credential'): static
    {
        return $this->state(fn (): array => ['token_hash' => hash('sha256', $token), 'token' => $token]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['token_hash' => null, 'token' => null, 'version' => 2]);
    }
}
