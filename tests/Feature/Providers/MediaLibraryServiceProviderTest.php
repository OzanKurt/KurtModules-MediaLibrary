<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Facades\Route;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\MediaLibraryAccess;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;
use Kurt\Modules\MediaLibrary\Policies\MediaLibraryFolderPolicy;
use Kurt\Modules\MediaLibrary\Policies\MediaLibraryItemPolicy;
use Kurt\Modules\MediaLibrary\Policies\SavedSearchPolicy;
use Kurt\Modules\MediaLibrary\Policies\ShareLinkPolicy;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Sharing\Support\AccessLogger;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkResolver;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkSigner;
use Kurt\Modules\MediaLibrary\Storage\Contracts\AiTagger;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\ExifExtractor;
use Kurt\Modules\MediaLibrary\Storage\Contracts\OcrExtractor;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Extractors\DefaultExifExtractor;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\ConversionEngine;
use Kurt\Modules\MediaLibrary\Storage\Support\FocalPointCropper;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\ReplaceCoordinator;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;
use Kurt\Modules\MediaLibrary\Storage\Support\VariantGenerator;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;

it('binds Support\MediaLibrary as a singleton', function (): void {
    $a = app(MediaLibrary::class);
    $b = app(MediaLibrary::class);

    expect($a)->toBeInstanceOf(MediaLibrary::class);
    expect($a === $b)->toBeTrue();
});

it('binds the default subject resolver', function (): void {
    $resolver = app(MediaSubjectResolver::class);

    expect($resolver)->toBeInstanceOf(DefaultSubjectResolver::class);
});

it('binds the default blurhash extractor per config', function (): void {
    expect(app(BlurhashGenerator::class))
        ->toBeInstanceOf(InterventionBlurhashGenerator::class);
});

it('binds the default palette extractor per config', function (): void {
    expect(app(PaletteExtractor::class))
        ->toBeInstanceOf(InterventionPaletteExtractor::class);
});

it('binds the default exif extractor per config', function (): void {
    // exif ships a real default and is run by the async ExtractMediaMetadata job.
    expect(app(ExifExtractor::class))->toBeInstanceOf(DefaultExifExtractor::class);
});

it('leaves ocr / ai_tagger unbound until a consumer configures an engine', function (): void {
    // These stay pluggable stubs (config null) and are skipped gracefully by the
    // pipeline; the package ships no OCR / AI engine.
    expect(app()->bound(OcrExtractor::class))->toBeFalse();
    expect(app()->bound(AiTagger::class))->toBeFalse();
});

it('binds support services as singletons', function (): void {
    foreach ([
        FocalPointCropper::class,
        ShareLinkSigner::class,
        ShareLinkResolver::class,
        AccessLogger::class,
        MetadataExtractor::class,
        UploadCoordinator::class,
        ReplaceCoordinator::class,
        VariantGenerator::class,
        ConversionEngine::class,
    ] as $abstract) {
        expect(app($abstract) === app($abstract))->toBeTrue("{$abstract} is not a singleton");
    }
});

it('binds MediaLibraryAccess in the scoped container', function (): void {
    // Scoped instances should resolve consistently within a single scope.
    $a = app(MediaLibraryAccess::class);
    $b = app(MediaLibraryAccess::class);

    expect($a)->toBeInstanceOf(MediaLibraryAccess::class);
    expect($a === $b)->toBeTrue();
});

it('publishes config under the media-library key', function (): void {
    expect(config('media-library.versions.keep_old'))->toBe(10);
    expect(config('media-library.uploads.disk'))->not->toBeNull();
});

it('registers observers globally on MediaLibraryItem and MediaLibraryFolder', function (): void {
    expect(MediaLibraryItem::getEventDispatcher())->not->toBeNull();
    expect(MediaLibraryFolder::getEventDispatcher())->not->toBeNull();
});

it('registers gate policies for items, folders, shares, and saved searches', function (): void {
    /** @var Gate $gate */
    $gate = app(Gate::class);

    expect($gate->getPolicyFor(MediaLibraryItem::class))->toBeInstanceOf(MediaLibraryItemPolicy::class);
    expect($gate->getPolicyFor(MediaLibraryFolder::class))->toBeInstanceOf(MediaLibraryFolderPolicy::class);
    expect($gate->getPolicyFor(ShareLink::class))->toBeInstanceOf(ShareLinkPolicy::class);
    expect($gate->getPolicyFor(MediaLibrarySavedSearch::class))->toBeInstanceOf(SavedSearchPolicy::class);
});

it('loads the share route when routes.share_enabled is true', function (): void {
    expect(Route::has('media-library.share.show'))->toBeTrue();
});
