<?php

namespace Database\Factories;

use App\Models\WeddingMediaAsset;
use App\Services\WeddingImageAssetStorage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WeddingMediaAsset>
 */
class WeddingMediaAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'kind' => 'image',
            'disk' => WeddingImageAssetStorage::DISK,
            'path' => fn (array $attributes): string => WeddingImageAssetStorage::path(value($attributes['wedding_id']), $attributes['id']),
            'mime_type' => WeddingImageAssetStorage::MIME_TYPE,
            'byte_size' => 120000,
            'width' => 1600,
            'height' => 1000,
        ];
    }
}
