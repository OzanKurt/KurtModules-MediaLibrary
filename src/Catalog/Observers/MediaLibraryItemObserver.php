<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Observers;

use Illuminate\Support\Facades\DB;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemDeleted;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemRestored;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

final class MediaLibraryItemObserver
{
    public function deleted(MediaLibraryItem $item): void
    {
        if ($item->folder_id !== null) {
            DB::table('media_library_folders')
                ->where('id', $item->folder_id)
                ->update([
                    'item_count' => DB::raw('CASE WHEN item_count > 0 THEN item_count - 1 ELSE 0 END'),
                ]);
        }

        ItemDeleted::dispatch($item);
    }

    public function restored(MediaLibraryItem $item): void
    {
        if ($item->folder_id !== null) {
            DB::table('media_library_folders')
                ->where('id', $item->folder_id)
                ->increment('item_count');
        }

        ItemRestored::dispatch($item);
    }
}
