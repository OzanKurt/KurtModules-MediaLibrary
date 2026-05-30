<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament;

use Filament\Contracts\Plugin;
use Kurt\Modules\Core\Support\FilamentVersion;

/**
 * Version-dispatching facade for the Media Library Filament plugin.
 *
 * Register on a panel with
 * `->plugin(\Kurt\Modules\MediaLibrary\Filament\MediaLibraryPlugin::make())`.
 * The correct V{n} plugin is resolved from the installed Filament major, so the
 * same call works whether the consumer runs Filament 3, 4, or 5.
 */
final class MediaLibraryPlugin
{
    public static function make(): Plugin
    {
        return match (FilamentVersion::major()) {
            5 => new V5\MediaLibraryPlugin,
            4 => new V4\MediaLibraryPlugin,
            3 => new V3\MediaLibraryPlugin,
            default => throw new \RuntimeException('Filament is not installed; cannot register the Media Library plugin.'),
        };
    }
}
