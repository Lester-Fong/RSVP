<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GuestFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name()];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['withdrawn_at' => null]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (): array => ['withdrawn_at' => now()]);
    }
}
