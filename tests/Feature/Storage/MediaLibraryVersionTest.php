<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;

it('persists a version row pointing at an item', function () {
    $item = MediaLibraryItem::factory()->create();
    $version = MediaLibraryVersion::factory()->create([
        'item_id' => $item->id,
        'spatie_media_id' => 99,
        'filename' => 'old.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 42_000,
        'changelog' => 'Re-shot in 4K',
    ]);

    expect($version->item?->id)->toBe($item->id);
    expect($version->spatie_media_id)->toBe(99);
    expect($version->byte_size)->toBe(42_000);
});

it('exposes versions via the item relation', function () {
    $item = MediaLibraryItem::factory()->create();
    MediaLibraryVersion::factory()->count(3)->create(['item_id' => $item->id]);

    expect($item->versions()->count())->toBe(3);
});
