<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;

final class UploadInitiated
{
    use Dispatchable;

    public function __construct(public readonly MediaLibraryPendingUpload $upload) {}
}
