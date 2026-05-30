<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;

final class ItemUntagged
{
    use Dispatchable;

    public function __construct(
        public readonly MediaLibraryItem $item,
        public readonly MediaLibraryTag $tag,
    ) {}
}
