<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Kurt\Modules\Core\Support\FilamentVersion;
use Kurt\Modules\MediaLibrary\Filament\MediaLibraryPlugin;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryFolderResource;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryItemResource;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryTagResource;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\ShareLinkResource;

beforeEach(function () {
    if (FilamentVersion::major() !== 3) {
        $this->markTestSkipped('Filament v3 is not installed.');
    }
});

it('dispatches the facade to the v3 plugin', function () {
    expect(MediaLibraryPlugin::make())->toBeInstanceOf(Kurt\Modules\MediaLibrary\Filament\V3\MediaLibraryPlugin::class)
        ->and(MediaLibraryPlugin::make()->getId())->toBe('kurtmodules-media-library');
});

it('registers all four media library resources on the panel', function () {
    $resources = Filament::getPanel('admin')->getResources();

    expect($resources)
        ->toContain(MediaLibraryItemResource::class)
        ->toContain(MediaLibraryFolderResource::class)
        ->toContain(MediaLibraryTagResource::class)
        ->toContain(ShareLinkResource::class);
});

it('registers routes for every resource', function () {
    $uris = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->all();

    expect($uris)
        ->toContain('admin/media-library-items', 'admin/media-library-items/{record}/edit')
        ->toContain('admin/media-library-folders', 'admin/media-library-folders/create')
        ->toContain('admin/media-library-tags')
        ->toContain('admin/share-links', 'admin/share-links/{record}');
});
