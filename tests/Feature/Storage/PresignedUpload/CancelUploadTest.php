<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionBlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Extractors\InterventionPaletteExtractor;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataExtractor;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;

function buildCancelCoordinator(): UploadCoordinator
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

beforeEach(function (): void {
    Storage::fake('s3');
    config()->set('media-library.uploads.disk', 's3');
});

it('marks a pending upload as Cancelled', function (): void {
    $coordinator = buildCancelCoordinator();
    $pending = MediaLibraryPendingUpload::factory()->create();

    $coordinator->cancelUpload($pending->upload_id);

    expect($pending->fresh()?->status)->toBe(PendingUploadStatus::Cancelled);
});

it('is a no-op when the pending row is already cancelled or completed', function (): void {
    $coordinator = buildCancelCoordinator();

    $completed = MediaLibraryPendingUpload::factory()->completed()->create();
    $coordinator->cancelUpload($completed->upload_id);
    expect($completed->fresh()?->status)->toBe(PendingUploadStatus::Completed);

    $expired = MediaLibraryPendingUpload::factory()->expired()->create();
    $coordinator->cancelUpload($expired->upload_id);
    expect($expired->fresh()?->status)->toBe(PendingUploadStatus::Expired);
});

it('does not throw when the pending row is missing', function (): void {
    $coordinator = buildCancelCoordinator();

    $coordinator->cancelUpload('missing-upload-id');
    // Reaching this line is success
    expect(true)->toBeTrue();
});
