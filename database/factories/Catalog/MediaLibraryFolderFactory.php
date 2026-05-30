<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Catalog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

/**
 * @extends Factory<MediaLibraryFolder>
 */
class MediaLibraryFolderFactory extends Factory
{
    /** @var class-string<MediaLibraryFolder> */
    protected $model = MediaLibraryFolder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        $slug = str($name)->slug()->toString();

        return [
            'owner_type' => 'stub_owner',
            'owner_id' => $this->faker->numberBetween(1, 1000),
            'parent_id' => null,
            'slug' => $slug,
            'name' => ['en' => $name],
            'description' => null,
            'path' => '/'.$slug,
            'depth' => 0,
            'position' => 0,
            'visibility' => Visibility::Private,
            'item_count' => 0,
            'descendant_count' => 0,
        ];
    }
}
