<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemDeleted;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemRestored;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Observers\MediaLibraryItemObserver;

beforeEach(function (): void {
    MediaLibraryItem::observe(MediaLibraryItemObserver::class);
});

afterEach(function (): void {
    MediaLibraryItem::flushEventListeners();
});

it('decrements folder item_count when an item is soft-deleted', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['item_count' => 5]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    $item->delete();

    expect($folder->fresh()->item_count)->toBe(4);
});

it('does not drop item_count below 0 when decrementing', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['item_count' => 0]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    $item->delete();

    expect($folder->fresh()->item_count)->toBe(0);
});

it('increments folder item_count when an item is restored', function (): void {
    $folder = MediaLibraryFolder::factory()->create(['item_count' => 5]);
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);
    $item->delete();

    // Reset the folder count to simulate independent restore state.
    $folder->update(['item_count' => 3]);

    $item->restore();

    expect($folder->fresh()->item_count)->toBe(4);
});

it('dispatches ItemDeleted on delete', function (): void {
    $item = MediaLibraryItem::factory()->create();

    Event::fake([ItemDeleted::class]);

    $item->delete();

    Event::assertDispatched(
        ItemDeleted::class,
        fn (ItemDeleted $event): bool => $event->item->is($item),
    );
});

it('dispatches ItemRestored on restore', function (): void {
    $item = MediaLibraryItem::factory()->create();
    $item->delete();

    Event::fake([ItemRestored::class]);

    $item->restore();

    Event::assertDispatched(
        ItemRestored::class,
        fn (ItemRestored $event): bool => $event->item->is($item),
    );
});
