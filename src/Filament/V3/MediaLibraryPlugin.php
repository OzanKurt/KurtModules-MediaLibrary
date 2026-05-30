<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Filament\V3;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryFolderResource;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryItemResource;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\MediaLibraryTagResource;
use Kurt\Modules\MediaLibrary\Filament\V3\Resources\ShareLinkResource;

final class MediaLibraryPlugin implements Plugin
{
    public function getId(): string
    {
        return 'kurtmodules-media-library';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            MediaLibraryItemResource::class,
            MediaLibraryFolderResource::class,
            MediaLibraryTagResource::class,
            ShareLinkResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        /** @var static */
        return app(self::class);
    }
}
