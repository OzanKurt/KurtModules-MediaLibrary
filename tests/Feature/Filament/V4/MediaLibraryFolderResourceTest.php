<?php

declare(strict_types=1);

use Kurt\Modules\Core\Support\FilamentVersion;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryFolderResource;
use Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryFolderResource\Pages\CreateMediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Filament\V4\Resources\MediaLibraryFolderResource\Pages\ListMediaLibraryFolders;

beforeEach(function () {
    if (FilamentVersion::major() !== 4) {
        $this->markTestSkipped('Filament v4 is not installed.');
    }
});

it('targets the MediaLibraryFolder model and registers its pages', function () {
    expect(MediaLibraryFolderResource::getModel())->toBe(MediaLibraryFolder::class)
        ->and(array_keys(MediaLibraryFolderResource::getPages()))->toBe(['index', 'create', 'edit']);
});

it('builds a translatable form with parent, visibility and position fields', function () {
    $fields = formFieldNames(MediaLibraryFolderResource::class, CreateMediaLibraryFolder::class);

    expect($fields)
        ->toContain('name.en', 'name.tr')
        ->toContain('description.en', 'description.tr')
        ->toContain('parent_id', 'visibility', 'position');
});

it('builds a tree-aware table with a visibility badge and item count', function () {
    expect(tableColumnNames(MediaLibraryFolderResource::class, ListMediaLibraryFolders::class))
        ->toContain('name', 'path', 'visibility', 'item_count', 'parent.name');

    expect(tableFilterNames(MediaLibraryFolderResource::class, ListMediaLibraryFolders::class))
        ->toContain('visibility');
});
