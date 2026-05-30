<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Catalog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryStorage;

/**
 * @extends Factory<MediaLibraryItem>
 */
class MediaLibraryItemFactory extends Factory
{
    /** @var class-string<MediaLibraryItem> */
    protected $model = MediaLibraryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);
        $filename = $this->faker->unique()->word().'.jpg';

        return [
            'owner_type' => 'stub_owner',
            'owner_id' => $this->faker->numberBetween(1, 1000),
            'folder_id' => null,
            'storage_id' => MediaLibraryStorage::factory(),
            'slug' => str($title)->slug()->toString(),
            'title' => ['en' => $title],
            'alt_text' => null,
            'caption' => null,
            'description' => null,
            'filename' => $filename,
            'mime_type' => 'image/jpeg',
            'byte_size' => $this->faker->numberBetween(1024, 1_000_000),
            'width' => 1920,
            'height' => 1080,
            'focal_x' => 0.5,
            'focal_y' => 0.5,
            'download_count' => 0,
            'view_count' => 0,
        ];
    }
}
