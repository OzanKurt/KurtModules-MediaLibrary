<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Support;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Throwable;

final class MetadataExtractor
{
    public function __construct(
        private readonly BlurhashGenerator $blurhash,
        private readonly PaletteExtractor $palette,
    ) {}

    /**
     * Extracts image dimensions, blurhash, dominant color, and palette
     * from a file on disk. Returns an array with the keys:
     *  - width: int|null
     *  - height: int|null
     *  - blurhash: string|null
     *  - dominant_color: string|null
     *  - palette: array<int, string>
     *
     * @return array<string, mixed>
     */
    public function extractSync(string $path, string $mimeType): array
    {
        $meta = [
            'width' => null,
            'height' => null,
            'blurhash' => null,
            'dominant_color' => null,
            'palette' => [],
        ];

        if (! str_starts_with($mimeType, 'image/') || ! is_readable($path)) {
            return $meta;
        }

        $driver = self::resolveDriver();

        if ($driver === null) {
            return $meta;
        }

        try {
            $manager = new ImageManager($driver);
            $img = $manager->read($path);

            $meta['width'] = $img->width();
            $meta['height'] = $img->height();
            $meta['blurhash'] = $this->blurhash->generate($path);

            $palette = $this->palette->extract($path);
            $meta['dominant_color'] = $palette['dominant'];
            $meta['palette'] = $palette['palette'];
        } catch (Throwable) {
            // best-effort; leave nulls
        }

        return $meta;
    }

    private static function resolveDriver(): ?DriverInterface
    {
        if (extension_loaded('gd') && class_exists(GdDriver::class)) {
            return new GdDriver;
        }

        if (extension_loaded('imagick') && class_exists(ImagickDriver::class)) {
            return new ImagickDriver;
        }

        return null;
    }
}
