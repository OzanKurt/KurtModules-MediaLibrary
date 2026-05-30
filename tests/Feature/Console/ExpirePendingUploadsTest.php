<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Kurt\Modules\MediaLibrary\Console\Commands\ExpirePendingUploadsCommand;
use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;

beforeEach(function (): void {
    Artisan::registerCommand(new ExpirePendingUploadsCommand);
});

it('flips pending uploads past their expiry to the Expired status', function (): void {
    $stale = MediaLibraryPendingUpload::factory()->create([
        'status' => PendingUploadStatus::Pending,
        'expires_at' => now()->subMinutes(5),
    ]);

    $fresh = MediaLibraryPendingUpload::factory()->create([
        'status' => PendingUploadStatus::Pending,
        'expires_at' => now()->addHour(),
    ]);

    $completed = MediaLibraryPendingUpload::factory()->completed()->create([
        'expires_at' => now()->subHour(),
    ]);

    $exit = Artisan::call('media-library:expire-pending-uploads');

    expect($exit)->toBe(0);
    expect($stale->fresh()?->status)->toBe(PendingUploadStatus::Expired);
    expect($fresh->fresh()?->status)->toBe(PendingUploadStatus::Pending);
    expect($completed->fresh()?->status)->toBe(PendingUploadStatus::Completed);
});
