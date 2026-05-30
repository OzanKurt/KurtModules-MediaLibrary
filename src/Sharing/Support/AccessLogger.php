<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Support;

use Illuminate\Database\Eloquent\Model;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;
use Kurt\Modules\MediaLibrary\Sharing\Models\AccessLogEntry;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

final class AccessLogger
{
    public function log(?MediaLibraryItem $item, ?ShareLink $link, ?Model $user, AccessAction $action): void
    {
        if (! (bool) config('media-library.access_log.enabled', true)) {
            return;
        }

        if ($action === AccessAction::View && ! (bool) config('media-library.access_log.on_view', true)) {
            return;
        }

        if ($action === AccessAction::Download && ! (bool) config('media-library.access_log.on_download', true)) {
            return;
        }

        $request = request();

        AccessLogEntry::create([
            'item_id' => $item?->id,
            'share_link_id' => $link?->id,
            'user_id' => $user?->getKey(),
            'action' => $action,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'occurred_at' => now(),
        ]);
    }
}
