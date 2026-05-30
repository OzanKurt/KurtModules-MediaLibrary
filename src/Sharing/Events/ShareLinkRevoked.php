<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

final class ShareLinkRevoked
{
    use Dispatchable;

    public function __construct(public readonly ShareLink $link) {}
}
