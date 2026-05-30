<?php

declare(strict_types=1);

use Kurt\Modules\Core\Support\FilamentVersion;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryItemResource;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryItemResource\Pages\EditMediaLibraryItem;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryItemResource\Pages\ListMediaLibraryItems;

beforeEach(function () {
    if (FilamentVersion::major() !== 3) {
        $this->markTestSkipped('Filament v3 is not installed.');
    }
});

it('targets the MediaLibraryItem model and registers a list + edit page (no create)', function () {
    expect(MediaLibraryItemResource::getModel())->toBe(MediaLibraryItem::class)
        ->and(array_keys(MediaLibraryItemResource::getPages()))->toBe(['index', 'edit']);
});

it('builds a translatable metadata form with focal point, folder, tags and read-only file fields', function () {
    $fields = formFieldNames(MediaLibraryItemResource::class, EditMediaLibraryItem::class);

    expect($fields)
        // Translatable metadata per locale (en, tr).
        ->toContain('title.en', 'title.tr')
        ->toContain('alt_text.en', 'alt_text.tr')
        ->toContain('caption.en', 'caption.tr')
        ->toContain('description.en', 'description.tr')
        // Placement.
        ->toContain('folder_id', 'tags')
        // Focal point numeric inputs.
        ->toContain('focal_x', 'focal_y')
        // Read-only file details.
        ->toContain('filename', 'mime_type', 'byte_size');
});

it('builds a table with key columns and mime + folder filters', function () {
    expect(tableColumnNames(MediaLibraryItemResource::class, ListMediaLibraryItems::class))
        ->toContain('title', 'mime_type', 'byte_size', 'folder.name', 'view_count', 'download_count', 'created_at');

    expect(tableFilterNames(MediaLibraryItemResource::class, ListMediaLibraryItems::class))
        ->toContain('mime_type', 'folder_id');
});

it('exposes edit, delete and bulk delete actions', function () {
    expect(tableActionNames(MediaLibraryItemResource::class, ListMediaLibraryItems::class))
        ->toContain('edit', 'delete');

    expect(tableBulkActionNames(MediaLibraryItemResource::class, ListMediaLibraryItems::class))
        ->toContain('delete');
});
