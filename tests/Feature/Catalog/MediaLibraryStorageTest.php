<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryStorage;

it('persists a storage host with a unique uuid', function () {
    $uid = (string) Str::uuid();
    $storage = MediaLibraryStorage::factory()->create(['item_uid' => $uid]);

    expect($storage->item_uid)->toBe($uid);
    expect(MediaLibraryStorage::query()->where('item_uid', $uid)->count())->toBe(1);
});

it('registers the mli single-file media collection', function () {
    $storage = MediaLibraryStorage::factory()->create();

    $collections = collect($storage->getRegisteredMediaCollections());

    expect($collections->pluck('name')->all())->toContain('mli');
    expect($collections->firstWhere('name', 'mli')?->singleFile)->toBeTrue();
});

it('registers conversions from config', function () {
    config()->set('media-library.conversions', [
        'mini' => ['width' => 100, 'height' => 100, 'fit' => 'crop'],
        'wide' => ['width' => 800, 'height' => 0, 'fit' => 'fit'],
    ]);

    $storage = MediaLibraryStorage::factory()->create();
    $storage->registerMediaConversions(null);

    $names = collect($storage->mediaConversions)
        ->map(fn ($conversion) => $conversion->getName())
        ->all();

    expect($names)->toContain('mini')->toContain('wide');
});
