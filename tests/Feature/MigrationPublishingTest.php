<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Kurt\Modules\MediaLibrary\Providers\MediaLibraryServiceProvider;

it('registers publishable migration sources that point at real files', function () {
    $paths = ServiceProvider::pathsToPublish(MediaLibraryServiceProvider::class, 'modules-media-library-migrations');
    expect($paths)->not->toBeEmpty();
    foreach (array_keys($paths) as $source) {
        expect(is_file($source))->toBeTrue("publishable migration source does not exist on disk: {$source}");
    }
});

it('publishes one entry per migration file in the package', function () {
    $paths = ServiceProvider::pathsToPublish(MediaLibraryServiceProvider::class, 'modules-media-library-migrations');
    $packageMigrations = glob(__DIR__.'/../../database/migrations/*.php');
    expect(count($paths))->toBe(count($packageMigrations));
});
