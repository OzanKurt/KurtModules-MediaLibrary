<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Extractors;

use Kurt\Modules\MediaLibrary\Storage\Contracts\ExifExtractor;

/**
 * Real EXIF / dimension extractor backed by PHP core.
 *
 * - Dimensions come from getimagesize() (part of ext-standard; always available).
 * - EXIF (incl. GPS) comes from exif_read_data(), guarded by ext-exif. When the
 *   extension is missing the EXIF portion is skipped gracefully and only the
 *   dimensions are returned.
 *
 * Returned shape (any key may be absent):
 *   - width:  int|null
 *   - height: int|null
 *   - exif:   array<string, mixed>   scalar/array EXIF tags, GPS* included
 */
final class DefaultExifExtractor implements ExifExtractor
{
    /**
     * @param  bool|null  $exifAvailable  Force the ext-exif availability check
     *                                    (null = detect at runtime). Injectable
     *                                    so the graceful-skip path is testable.
     */
    public function __construct(private readonly ?bool $exifAvailable = null) {}

    /**
     * @return array<string, mixed>
     */
    public function extract(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $result = [];

        $size = @getimagesize($path);
        if (is_array($size)) {
            $result['width'] = (int) $size[0];
            $result['height'] = (int) $size[1];
        }

        if ($this->exifSupported()) {
            $data = @exif_read_data($path);

            if (is_array($data)) {
                // Keep only scalars and arrays (GPS tags arrive as arrays of
                // rational strings); drop binary/resource values that will not
                // survive a JSON round-trip into the item's `exif` column.
                $result['exif'] = array_filter(
                    $data,
                    static fn ($v): bool => is_scalar($v) || is_array($v),
                );
            }
        }

        return $result;
    }

    private function exifSupported(): bool
    {
        if ($this->exifAvailable !== null) {
            return $this->exifAvailable;
        }

        return function_exists('exif_read_data') && extension_loaded('exif');
    }
}
