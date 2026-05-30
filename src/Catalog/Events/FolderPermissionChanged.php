<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

final class FolderPermissionChanged
{
    use Dispatchable;

    public function __construct(
        public readonly MediaLibraryFolder $folder,
        public readonly FolderPermission $permission,
    ) {}
}
