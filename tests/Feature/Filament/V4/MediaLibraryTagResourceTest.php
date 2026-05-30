<?php

declare(strict_types=1);

use Kurt\Modules\Core\Support\FilamentVersion;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;
use Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryTagResource;
use Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryTagResource\Pages\CreateMediaLibraryTag;
use Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryTagResource\Pages\ListMediaLibraryTags;

beforeEach(function () {
    if (FilamentVersion::major() !== 4) {
        $this->markTestSkipped('Filament v4 is not installed.');
    }
});

it('targets the MediaLibraryTag model and registers its pages', function () {
    expect(MediaLibraryTagResource::getModel())->toBe(MediaLibraryTag::class)
        ->and(array_keys(MediaLibraryTagResource::getPages()))->toBe(['index', 'create', 'edit']);
});

it('builds a translatable name + color form', function () {
    $fields = formFieldNames(MediaLibraryTagResource::class, CreateMediaLibraryTag::class);

    expect($fields)
        ->toContain('name.en', 'name.tr')
        ->toContain('color', 'position');
});

it('builds a table with a color swatch and item count', function () {
    expect(tableColumnNames(MediaLibraryTagResource::class, ListMediaLibraryTags::class))
        ->toContain('color', 'name', 'items_count', 'position');
});
