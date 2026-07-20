<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

/**
 * Fix 2: the default upload disk must be private so that item bytes are only
 * reachable through the share-link controller (which enforces abilities, folder
 * ACL, policies and access logging) and never via a guessable public
 * /storage/{id}/{file} path.
 */
function buildPrivateDiskCoordinator(): UploadCoordinator
{
    /** @var BlurhashGenerator $blurhash */
    $blurhash = app(InterventionBlurhashGenerator::class);
    /** @var PaletteExtractor $palette */
    $palette = app(InterventionPaletteExtractor::class);

    return new UploadCoordinator(
        new DefaultSubjectResolver,
        new MetadataExtractor($blurhash, $palette),
    );
}

it('defaults the upload disk to a private disk, not public', function (): void {
    // No MEDIA_LIBRARY_DISK env set in the test environment, so the config
    // default is what a fresh install receives.
    expect(config('media-library.uploads.disk'))->not->toBe('public');
    expect(config('media-library.uploads.disk'))->toBe('local');
});

it('stores media on a private disk with no public URL, so it cannot be served directly', function (): void {
    Event::fake();
    Storage::fake('local');
    config()->set('media-library.uploads.disk', 'local');

    $owner = StubUser::create(['id' => 31, 'email' => 'private@test.dev']);

    $item = buildPrivateDiskCoordinator()->upload(
        new UploadedFile(__DIR__.'/../../fixtures/test.png', 'private-photo.png', 'image/png', null, true),
        $owner,
    );

    $media = $item->spatieMedia();
    expect($media)->not->toBeNull();

    // The object lives on the private disk, not on the public disk where spatie
    // would expose it at a guessable /storage path.
    expect($media?->disk)->toBe('local');
    expect(Storage::disk('local')->exists($media?->getPathRelativeToRoot() ?? ''))->toBeTrue();

    // The private disk exposes no public base URL: there is no addressable
    // public path, so bytes must be fetched through the share-link controller.
    expect(config('filesystems.disks.local.url'))->toBeNull();
});
