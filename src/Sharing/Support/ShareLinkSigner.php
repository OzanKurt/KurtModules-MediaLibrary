<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Support;

final class ShareLinkSigner
{
    public function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    /**
     * Deterministic hash of a share token. Stored on the share link and used as
     * the lookup key so the raw bearer token never becomes a query value.
     */
    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function url(string $token): string
    {
        $prefix = trim((string) config('media-library.routes.share_prefix', 'media-library/share'), '/');

        return url($prefix.'/'.$token);
    }
}
