<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Console\Commands\PruneVersionsCommand;
use Kurt\Modules\MediaLibrary\Storage\Events\VersionPruned;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;

beforeEach(function (): void {
    Artisan::registerCommand(new PruneVersionsCommand);
});

it('prunes versions older than keep_old retention', function (): void {
    Event::fake([VersionPruned::class]);
    config()->set('media-library.versions.keep_old', 10);

    $item = MediaLibraryItem::factory()->create();

    // Create 12 versions with staggered timestamps so the 2 oldest get pruned.
    for ($i = 0; $i < 12; $i++) {
        MediaLibraryVersion::factory()->create([
            'item_id' => $item->id,
            'created_at' => now()->subMinutes(12 - $i),
            'updated_at' => now()->subMinutes(12 - $i),
        ]);
    }

    expect(MediaLibraryVersion::query()->where('item_id', $item->id)->count())->toBe(12);

    $exit = Artisan::call('media-library:prune-versions');

    expect($exit)->toBe(0);
    expect(MediaLibraryVersion::query()->where('item_id', $item->id)->count())->toBe(10);

    Event::assertDispatchedTimes(VersionPruned::class, 2);
});

it('limits pruning to a single item when an id is given', function (): void {
    Event::fake([VersionPruned::class]);
    config()->set('media-library.versions.keep_old', 1);

    $kept = MediaLibraryItem::factory()->create();
    $target = MediaLibraryItem::factory()->create();

    foreach ([$kept, $target] as $item) {
        for ($i = 0; $i < 3; $i++) {
            MediaLibraryVersion::factory()->create([
                'item_id' => $item->id,
                'created_at' => now()->subMinutes(3 - $i),
                'updated_at' => now()->subMinutes(3 - $i),
            ]);
        }
    }

    $exit = Artisan::call('media-library:prune-versions', ['item' => $target->id]);

    expect($exit)->toBe(0);
    expect(MediaLibraryVersion::query()->where('item_id', $kept->id)->count())->toBe(3);
    expect(MediaLibraryVersion::query()->where('item_id', $target->id)->count())->toBe(1);
});
