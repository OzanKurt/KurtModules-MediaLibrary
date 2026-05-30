<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Catalog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;

/**
 * @extends Factory<MediaLibrarySavedSearch>
 */
class MediaLibrarySavedSearchFactory extends Factory
{
    /** @var class-string<MediaLibrarySavedSearch> */
    protected $model = MediaLibrarySavedSearch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 1,
            'name' => $this->faker->unique()->words(2, true),
            'filters' => ['mime_type' => 'image/*'],
        ];
    }
}
