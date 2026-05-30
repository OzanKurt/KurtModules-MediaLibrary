<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Storage\Extractors;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;
use kornrunner\Blurhash\Blurhash;
use Kurt\Modules\MediaLibrary\Storage\Contracts\BlurhashGenerator;
use Throwable;

final class InterventionBlurhashGenerator implements BlurhashGenerator
{
    public function generate(string $path): string
    {
        if (! class_exists(Blurhash::class) || ! is_readable($path)) {
            return '';
        }

        $driver = self::resolveDriver();

        if ($driver === null) {
            return '';
        }

        try {
            $manager = new ImageManager($driver);
            $img = $manager->read($path)->scaleDown(32);

            $pixels = [];
            for ($y = 0; $y < $img->height(); $y++) {
                $row = [];
                for ($x = 0; $x < $img->width(); $x++) {
                    $c = $img->pickColor($x, $y);

                    if (! method_exists($c, 'red') || ! method_exists($c, 'green') || ! method_exists($c, 'blue')) {
                        return '';
                    }

                    $row[] = [
                        $c->red()->value(),
                        $c->green()->value(),
                        $c->blue()->value(),
                    ];
                }
                $pixels[] = $row;
            }

            return Blurhash::encode($pixels, 4, 4);
        } catch (Throwable) {
            return '';
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
