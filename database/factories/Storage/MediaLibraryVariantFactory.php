<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Storage;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant;

/**
 * @extends Factory<MediaLibraryVariant>
 */
class MediaLibraryVariantFactory extends Factory
{
    /** @var class-string<MediaLibraryVariant> */
    protected $model = MediaLibraryVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = '300x200-crop-jpg-q85-'.$this->faker->unique()->numberBetween(1, 1_000_000);

        return [
            'item_id' => MediaLibraryItem::factory(),
            'key' => $key,
            'spec' => ['width' => 300, 'height' => 200, 'fit' => 'crop', 'format' => 'jpg', 'quality' => 85],
            'path' => 'media-library/variants/'.$this->faker->uuid().'/'.$key.'.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => $this->faker->numberBetween(1024, 200_000),
            'last_used_at' => now(),
            'generated_at' => now(),
        ];
    }
}
