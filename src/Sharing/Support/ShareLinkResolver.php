<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Support;

use Kurt\Modules\MediaLibrary\Exceptions\ShareLinkInvalid;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

final class ShareLinkResolver
{
    public function resolve(string $token): ShareLink
    {
        $link = ShareLink::query()->where('token', $token)->first();

        if ($link === null) {
            throw new ShareLinkInvalid('not_found');
        }

        if (! $link->isActive()) {
            throw new ShareLinkInvalid('inactive');
        }

        return $link;
    }
}
