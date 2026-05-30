<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Extractors;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use Kurt\Modules\MediaLibrary\Storage\Contracts\PaletteExtractor;
use Throwable;

final class InterventionPaletteExtractor implements PaletteExtractor
{
    /**
     * @return array{dominant: string, palette: array<int, string>}
     */
    public function extract(string $path): array
    {
        if (! is_readable($path)) {
            return ['dominant' => '#000000', 'palette' => []];
        }

        $driver = self::resolveDriver();

        if ($driver === null) {
            return ['dominant' => '#000000', 'palette' => []];
        }

        try {
            $manager = new ImageManager($driver);
            $img = $manager->read($path)->scaleDown(64);

            $counts = [];
            for ($y = 0; $y < $img->height(); $y++) {
                for ($x = 0; $x < $img->width(); $x++) {
                    $c = $img->pickColor($x, $y);

                    if (! method_exists($c, 'red') || ! method_exists($c, 'green') || ! method_exists($c, 'blue')) {
                        continue;
                    }

                    $hex = sprintf(
                        '#%02x%02x%02x',
                        $c->red()->value(),
                        $c->green()->value(),
                        $c->blue()->value(),
                    );
                    $counts[$hex] = ($counts[$hex] ?? 0) + 1;
                }
            }

            if ($counts === []) {
                return ['dominant' => '#000000', 'palette' => []];
            }

            arsort($counts);
            /** @var array<int, string> $palette */
            $palette = array_slice(array_keys($counts), 0, 5);
            $dominant = $palette[0] ?? '#000000';

            return ['dominant' => $dominant, 'palette' => $palette];
        } catch (Throwable) {
            return ['dominant' => '#000000', 'palette' => []];
        }
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
