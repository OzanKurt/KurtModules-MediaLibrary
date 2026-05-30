<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Support;

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

final class ConversionEngine
{
    /**
     * Resolve a URL for the given item + optional named conversion.
     * Thin wrapper over MediaLibraryItem::url() so consumers don't
     * need to reach into the model directly.
     */
    public function urlFor(MediaLibraryItem $item, ?string $conversion = null): string
    {
        return $item->url($conversion);
    }
}
