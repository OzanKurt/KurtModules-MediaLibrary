<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Storage\Enums\PendingUploadStatus;

it('has expected cases and values', function () {
    expect(PendingUploadStatus::Pending->value)->toBe('pending');
    expect(PendingUploadStatus::Completed->value)->toBe('completed');
    expect(PendingUploadStatus::Cancelled->value)->toBe('cancelled');
    expect(PendingUploadStatus::Expired->value)->toBe('expired');
});
