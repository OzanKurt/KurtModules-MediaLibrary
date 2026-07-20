<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Support;

use Kurt\Modules\MediaLibrary\Exceptions\ShareLinkInvalid;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

final class ShareLinkResolver
{
    private readonly ShareLinkSigner $signer;

    public function __construct(?ShareLinkSigner $signer = null)
    {
        $this->signer = $signer ?? new ShareLinkSigner;
    }

    public function resolve(string $token): ShareLink
    {
        // Resolve by the token's hash so the raw bearer token is never used as a
        // query key. Fall back to a plaintext match only as a safety net for rows
        // that somehow lack a backfilled hash.
        $link = ShareLink::query()
            ->where('token_hash', $this->signer->hashToken($token))
            ->first();

        if ($link === null) {
            $link = ShareLink::query()->where('token', $token)->first();
        }

        if ($link === null) {
            throw new ShareLinkInvalid('not_found');
        }

        if (! $link->isActive()) {
            throw new ShareLinkInvalid('inactive');
        }

        return $link;
    }
}
