<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;

it('persists a pending upload with enum status + json payload', function () {
    $upload = MediaLibraryPendingUpload::factory()->create([
        'driver_payload' => ['url' => 'https://s3.example/upload', 'key' => 'uploads/foo'],
    ]);

    expect($upload->status)->toBe(PendingUploadStatus::Pending);
    expect($upload->driver_payload['key'])->toBe('uploads/foo');
    expect($upload->expires_at?->isFuture())->toBeTrue();
});

it('completed state factory marks status + completed_at', function () {
    $upload = MediaLibraryPendingUpload::factory()->completed()->create();

    expect($upload->status)->toBe(PendingUploadStatus::Completed);
    expect($upload->completed_at)->not->toBeNull();
});

it('expired state factory marks status + past expires_at', function () {
    $upload = MediaLibraryPendingUpload::factory()->expired()->create();

    expect($upload->status)->toBe(PendingUploadStatus::Expired);
    expect($upload->expires_at?->isPast())->toBeTrue();
});

it('owner morphs through polymorphic columns', function () {
    $upload = MediaLibraryPendingUpload::factory()->create([
        'owner_type' => 'demo_tenant',
        'owner_id' => 7,
    ]);

    expect($upload->owner_type)->toBe('demo_tenant');
    expect($upload->owner_id)->toBe(7);
});
