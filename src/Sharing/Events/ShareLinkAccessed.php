<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

final class ShareLinkAccessed
{
    use Dispatchable;

    public function __construct(
        public readonly ShareLink $link,
        public readonly ?Authenticatable $user,
        public readonly string $action,
    ) {}
}
