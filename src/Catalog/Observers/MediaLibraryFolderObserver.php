<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Observers;

use Kurt\Modules\MediaLibrary\Catalog\Events\FolderCreated;
use Kurt\Modules\MediaLibrary\Catalog\Events\FolderDeleted;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

final class MediaLibraryFolderObserver
{
    public function creating(MediaLibraryFolder $folder): void
    {
        if (! $folder->path) {
            $parent = $folder->parent_id !== null ? $folder->parent : null;
            $parentPath = $parent !== null ? $parent->path : '';
            $parentDepth = $parent !== null ? $parent->depth : -1;
            $folder->path = $parentPath.'/'.$folder->slug;
            $folder->depth = $parentDepth + 1;
        }
    }

    public function created(MediaLibraryFolder $folder): void
    {
        FolderCreated::dispatch($folder);
    }

    public function deleted(MediaLibraryFolder $folder): void
    {
        FolderDeleted::dispatch($folder);
    }
}
