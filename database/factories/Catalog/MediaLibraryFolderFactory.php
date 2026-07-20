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
            'depth' => 0,
            'position' => 0,
            'visibility' => Visibility::Private,
            'item_count' => 0,
            'descendant_count' => 0,
        ];
    }

    public function configure(): static
    {
        // Synthesize path/depth/owner the same way production does (createFolder +
        // observer): a nested folder's `path` materialises its ancestry and a
        // subtree shares a single owner. Only runs when the caller did NOT pin a
        // path explicitly, so the observer / rebuild-paths / recursive-scope tests
        // that pass their own path keep exercising that code unchanged.
        return $this->afterMaking(function (MediaLibraryFolder $folder): void {
            if ($folder->path !== null) {
                return;
            }

            $parent = $folder->parent_id !== null
                ? MediaLibraryFolder::query()->find($folder->parent_id)
                : null;

            if ($parent === null) {
                $folder->path = '/'.$folder->slug;
                $folder->depth = 0;

                return;
            }

            $folder->owner_type = $parent->owner_type;
            $folder->owner_id = $parent->owner_id;
            $folder->path = rtrim($parent->path, '/').'/'.$folder->slug;
            $folder->depth = $parent->depth + 1;
        });
    }
}
