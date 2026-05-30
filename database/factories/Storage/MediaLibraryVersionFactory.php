<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Storage;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;

/**
 * @extends Factory<MediaLibraryVersion>
 */
class MediaLibraryVersionFactory extends Factory
{
    /** @var class-string<MediaLibraryVersion> */
    protected $model = MediaLibraryVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => MediaLibraryItem::factory(),
            'spatie_media_id' => $this->faker->numberBetween(1, 1_000_000),
            'filename' => $this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => $this->faker->numberBetween(1024, 1_000_000),
            'changelog' => null,
            'created_by' => null,
        ];
    }
}
