<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

it('persists translatable name + slug + cast visibility', function () {
    $folder = MediaLibraryFolder::factory()->create([
        'name' => ['en' => 'Marketing', 'tr' => 'Pazarlama'],
        'visibility' => Visibility::Restricted,
    ]);

    expect($folder->getTranslation('name', 'en'))->toBe('Marketing');
    expect($folder->getTranslation('name', 'tr'))->toBe('Pazarlama');
    expect($folder->slug)->not->toBeEmpty();
    expect($folder->visibility)->toBe(Visibility::Restricted);
});

it('relates parent and children', function () {
    $parent = MediaLibraryFolder::factory()->create();
    $child = MediaLibraryFolder::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent?->id)->toBe($parent->id);
    expect($parent->children()->pluck('id')->all())->toContain($child->id);
});

it('relates items inside the folder', function () {
    $folder = MediaLibraryFolder::factory()->create();
    $item = MediaLibraryItem::factory()->create(['folder_id' => $folder->id]);

    expect($folder->items()->pluck('id')->all())->toContain($item->id);
});

it('soft deletes', function () {
    $folder = MediaLibraryFolder::factory()->create();
    $folder->delete();

    expect($folder->trashed())->toBeTrue();
    expect(MediaLibraryFolder::withTrashed()->find($folder->id))->not->toBeNull();
});
