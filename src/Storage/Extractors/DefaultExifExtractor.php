<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Extractors;

use Kurt\Modules\MediaLibrary\Storage\Contracts\ExifExtractor;

final class DefaultExifExtractor implements ExifExtractor
{
    /**
     * @return array<string, mixed>
     */
    public function extract(string $path): array
    {
        if (! function_exists('exif_read_data') || ! is_readable($path)) {
            return [];
        }

        $data = @exif_read_data($path);

        if (! is_array($data)) {
            return [];
        }

        return array_filter($data, static fn ($v): bool => is_scalar($v) || is_array($v));
    }
}
