<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

final class ExifExtracted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $exif
     */
    public function __construct(
        public readonly MediaLibraryItem $item,
        public readonly array $exif,
    ) {}
}
