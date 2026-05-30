<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

final class DemoCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:demo';

    /** @var string */
    protected $description = 'Seed a sample folder structure + items for local development demos.';

    public function handle(): int
    {
        $ownerType = 'stub_owner';
        $ownerId = (int) (config('media-library.demo.owner_id') ?? 1);

        $root = MediaLibraryFolder::factory()->create([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'name' => ['en' => 'Demo'],
        ]);

        MediaLibraryFolder::factory()->create([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'parent_id' => $root->id,
            'name' => ['en' => 'Subfolder'],
        ]);

        MediaLibraryItem::factory()
            ->count(5)
            ->create([
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'folder_id' => $root->id,
            ]);

        $this->info("Demo seeded with owner #{$ownerId}, folders, and 5 items.");

        return self::SUCCESS;
    }
}
