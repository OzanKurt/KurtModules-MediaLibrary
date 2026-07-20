<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Support\Concerns;

use Illuminate\Support\Str;
use Kurt\Modules\MediaLibrary\Exceptions\InvalidUpload;

/**
 * Shared upload-hardening primitives used by every code path that accepts a
 * client-supplied file (server-proxy upload, presigned finalize, replace).
 * Keeping them in one trait guarantees the replace flow enforces the exact
 * same mime allow-list, size limit, and filename sanitisation as the initial
 * upload flow, so replacement can never be used to bypass those guards.
 */
trait HardensUploads
{
    /**
     * Strip directory components and slug a client-supplied filename so it is
     * safe to use as a storage filename / S3 key segment. The extension is
     * preserved (lower-cased, alphanumeric only) so mime/type handling and
     * downloads still work.
     */
    protected function sanitizeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));

        $extension = strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', pathinfo($name, PATHINFO_EXTENSION)));
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME));

        if ($base === '') {
            $base = 'file';
        }

        return $extension === '' ? $base : $base.'.'.$extension;
    }

    protected function assertMimeAllowed(string $mimeType): void
    {
        /** @var array<int, mixed> $allowed */
        $allowed = (array) config('media-library.uploads.allowed_mimes', []);

        if ($allowed === []) {
            return;
        }

        foreach ($allowed as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            if (str_contains($pattern, '*')) {
                $regex = '#^'.str_replace(['/', '*'], ['\/', '.*'], $pattern).'$#i';
                if (preg_match($regex, $mimeType) === 1) {
                    return;
                }

                continue;
            }

            if (strcasecmp($pattern, $mimeType) === 0) {
                return;
            }
        }

        throw new InvalidUpload(sprintf('mime type "%s" is not allowed', $mimeType));
    }

    protected function assertSizeAllowed(?int $byteSize): void
    {
        if ($byteSize === null) {
            return;
        }

        $maxKb = (int) config('media-library.uploads.max_size_kb', 0);

        if ($maxKb <= 0) {
            return;
        }

        $maxBytes = $maxKb * 1024;

        if ($byteSize > $maxBytes) {
            throw new InvalidUpload(sprintf('upload size %d bytes exceeds limit of %d kb', $byteSize, $maxKb));
        }
    }
}
