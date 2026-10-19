<?php

namespace Database\Factories;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvitationGroupFactory extends Factory
{
    public function definition(): array
    {
        return ['label' => fake()->lastName().' Family', 'contact_email' => null, 'contact_phone' => null];
    }

    public function withGuests(int $count = 1): static
    {
        return $this->has(Guest::factory()->count($count));
    }
}
