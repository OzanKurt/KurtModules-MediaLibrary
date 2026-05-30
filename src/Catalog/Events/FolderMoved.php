<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

final class FolderMoved
{
    use Dispatchable;

    public function __construct(
        public readonly MediaLibraryFolder $folder,
        public readonly ?int $oldParentId,
        public readonly ?int $newParentId,
    ) {}
}
