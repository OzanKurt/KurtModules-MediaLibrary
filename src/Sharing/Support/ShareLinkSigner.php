<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Sharing\Support;

final class ShareLinkSigner
{
    public function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    public function url(string $token): string
    {
        $prefix = trim((string) config('media-library.routes.share_prefix', 'media-library/share'), '/');

        return url($prefix.'/'.$token);
    }
}
