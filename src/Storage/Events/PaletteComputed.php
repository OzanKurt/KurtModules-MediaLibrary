<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

final class PaletteComputed
{
    use Dispatchable;

    /**
     * @param  array<int, string>  $palette
     */
    public function __construct(
        public readonly MediaLibraryItem $item,
        public readonly string $dominant,
        public readonly array $palette,
    ) {}
}
