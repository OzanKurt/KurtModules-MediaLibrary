<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\MediaLibrary\Catalog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryStorage;

/**
 * @extends Factory<MediaLibraryStorage>
 */
class MediaLibraryStorageFactory extends Factory
{
    /** @var class-string<MediaLibraryStorage> */
    protected $model = MediaLibraryStorage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_uid' => (string) Str::uuid(),
        ];
    }
}
