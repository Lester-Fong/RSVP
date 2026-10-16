<?php

namespace Database\Factories;

use App\Models\WebsiteDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteDraft>
 */
class WebsiteDraftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'schema_version' => WebsiteDraft::SCHEMA_VERSION,
            'lock_version' => 1,
            'content' => WebsiteDraft::defaultContent(),
        ];
    }
}
