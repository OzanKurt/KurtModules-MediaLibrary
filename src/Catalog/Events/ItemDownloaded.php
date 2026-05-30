<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

final class ItemDownloaded
{
    use Dispatchable;

    public function __construct(
        public readonly MediaLibraryItem $item,
        public readonly ?Authenticatable $user,
    ) {}
}
