<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;

it('persists translatable name + slug', function () {
    $tag = MediaLibraryTag::factory()->create(['name' => ['en' => 'Autumn']]);

    expect($tag->getTranslation('name', 'en'))->toBe('Autumn');
    expect($tag->slug)->not->toBeEmpty();
});

it('relates items via pivot', function () {
    $tag = MediaLibraryTag::factory()->create();
    $item = MediaLibraryItem::factory()->create();
    $item->tags()->attach($tag);

    expect($tag->items()->pluck('media_library_items.id')->all())->toContain($item->id);
});
