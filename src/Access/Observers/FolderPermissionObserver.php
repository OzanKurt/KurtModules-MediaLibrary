<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Observers;

use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Catalog\Events\FolderPermissionChanged;

/**
 * Emits {@see FolderPermissionChanged} on every permission mutation — grant
 * (created), edit (updated), and revoke (deleted). The model is the single
 * chokepoint for permission rows, so observing it makes the event fire no
 * matter which write path (API controller, Filament, console, future code)
 * touched the row. That event is the ACL cache's bump signal, so a missed
 * dispatch would mean stale access after a revoke — the exact hole to avoid.
 */
final class FolderPermissionObserver
{
    public function created(FolderPermission $permission): void
    {
        $this->dispatch($permission);
    }

    public function updated(FolderPermission $permission): void
    {
        $this->dispatch($permission);
    }

    public function deleted(FolderPermission $permission): void
    {
        $this->dispatch($permission);
    }

    private function dispatch(FolderPermission $permission): void
    {
        $folder = $permission->folder;

        if ($folder !== null) {
            FolderPermissionChanged::dispatch($folder, $permission);
        }
    }
}
