<?php

declare(strict_types=1);

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    // Fake an "s3" disk locally. We then teach it how to issue a temporary
    // upload URL via FilesystemAdapter::buildTemporaryUploadUrlsUsing so
    // UploadCoordinator::initiateUpload doesn't blow up.
    $fake = Storage::fake('s3');
    expect($fake)->toBeInstanceOf(FilesystemAdapter::class);
    $fake->buildTemporaryUploadUrlsUsing(
        fn (string $path, $expiration, array $options = []): array => [
            'url' => 'https://s3.example/fake/'.$path,
            'headers' => ['Content-Type' => $options['ContentType'] ?? 'application/octet-stream'],
        ],
    );

    config()->set('media-library.uploads.disk', 's3');
});

it('initiates a pending upload then completes it after the file lands on the disk', function (): void {
    $owner = StubUser::create(['email' => 'presigned@test.dev']);
    $this->be($owner);

    $library = app(MediaLibrary::class);

    $pending = $library->initiateUpload($owner, [
        'filename' => 'presigned.png',
        'mime_type' => 'image/png',
        'byte_size' => 1024,
    ]);

    expect($pending)->toBeInstanceOf(MediaLibraryPendingUpload::class);
    expect($pending->status)->toBe(PendingUploadStatus::Pending);
    expect($pending->driver_payload['key'])->toContain('media-library/incoming/');
    expect($pending->driver_payload['url'])->toContain('https://s3.example/fake/');

    // Simulate the client PUTting the file directly to the presigned URL —
    // the object now exists on the s3 disk.
    $key = (string) $pending->driver_payload['key'];
    Storage::disk('s3')->put($key, file_get_contents(__DIR__.'/../../fixtures/test.png'));

    expect(Storage::disk('s3')->exists($key))->toBeTrue();

    $item = $library->completeUpload($pending->upload_id);

    expect($item)->toBeInstanceOf(MediaLibraryItem::class);
    expect($item->filename)->toBe('presigned.png');
    expect($item->mime_type)->toBe('image/png');
    expect($item->byte_size)->toBeGreaterThan(0);

    expect($pending->fresh()?->status)->toBe(PendingUploadStatus::Completed);
    expect($pending->fresh()?->completed_at)->not->toBeNull();
});

it('cancelUpload flips a pending row to Cancelled', function (): void {
    $owner = StubUser::create(['email' => 'cancel@test.dev']);
    $this->be($owner);

    $library = app(MediaLibrary::class);
    $pending = $library->initiateUpload($owner, [
        'filename' => 'cancel.png',
        'mime_type' => 'image/png',
        'byte_size' => 1024,
    ]);

    $library->cancelUpload($pending->upload_id);

    expect($pending->fresh()?->status)->toBe(PendingUploadStatus::Cancelled);
});
