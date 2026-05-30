<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;

final class VersionPruned
{
    use Dispatchable;

    public function __construct(public readonly MediaLibraryVersion $version) {}
}
