<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryAttachment;

final class ItemDetached
{
    use Dispatchable;

    public function __construct(public readonly MediaLibraryAttachment $attachment) {}
}
