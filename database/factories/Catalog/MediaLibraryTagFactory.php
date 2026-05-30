<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Catalog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;

/**
 * @extends Factory<MediaLibraryTag>
 */
class MediaLibraryTagFactory extends Factory
{
    /** @var class-string<MediaLibraryTag> */
    protected $model = MediaLibraryTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'owner_type' => 'stub_owner',
            'owner_id' => $this->faker->numberBetween(1, 1000),
            'slug' => str($name)->slug()->toString(),
            'name' => ['en' => $name],
            'color' => null,
            'position' => 0,
        ];
    }
}
