<?php

namespace Database\Factories;

use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteRevision>
 */
class WebsiteRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'revision_number' => 1,
            'schema_version' => WebsiteDraft::SCHEMA_VERSION,
            'wedding_facts' => ['partner_one_name' => 'Alex', 'partner_two_name' => 'Jamie', 'wedding_date' => '2027-05-12', 'wedding_time' => null, 'ceremony_venue' => null, 'reception_venue' => null],
            'content' => WebsiteDraft::defaultContent(),
            'fingerprint' => hash('sha256', 'synthetic snapshot'),
            'published_at' => now(),
        ];
    }
}
